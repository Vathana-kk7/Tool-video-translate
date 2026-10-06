<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SyncAudioService
{
    protected TTSService $tts;
    protected string $tempPath;

    public function __construct(TTSService $tts)
    {
        $this->tts      = $tts;
        $this->tempPath = storage_path('app/temp_sync');

        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
    }

    public function buildSyncedAudio(
        array $segments,
        float $videoDuration,
        string $outputPath
    ): string {
        $sessionId    = uniqid('sync_');
        $cacheId      = hash('sha256', $outputPath);
        $segmentFiles = [];
        $mergedAudio  = null;
        $completed    = false;

        if ($videoDuration <= 0) {
            throw new \InvalidArgumentException('Video duration must be greater than zero.');
        }

        try {
            Log::info('Starting synced audio build', [
                'segment_count'  => count($segments),
                'video_duration' => $videoDuration,
            ]);

            $ttsJobs = [];
            foreach ($segments as $i => $segment) {
                $start    = (float) ($segment['start'] ?? 0);
                $end      = (float) ($segment['end'] ?? 0);
                $duration = $end - $start;
                $khmerText = trim($segment['translated'] ?? '');

                if ($duration <= 0) {
                    Log::warning("Segment {$i} skipped", [
                        'reason'   => 'invalid duration',
                        'duration' => $duration,
                    ]);
                    continue;
                }

                if ($khmerText === '') {
                    throw new \RuntimeException("Segment {$i} is missing its Khmer translation.");
                }

                $textHash = hash('sha256', $khmerText);
                $segFile = "{$this->tempPath}/video_{$cacheId}_seg_{$i}_{$textHash}.mp3";
                $segmentFiles[] = [
                    'file'     => $segFile,
                    'start'    => $start,
                    'end'      => $end,
                    'duration' => $duration,
                    'text'     => $khmerText,
                ];

                $ttsJobs[] = [
                    'text' => $khmerText,
                    'output_path' => $segFile,
                    'duration' => $duration,
                ];
            }

            $this->tts->generateSpeechWithTimingBatch($ttsJobs);

            foreach ($segmentFiles as $i => $segmentFile) {
                if (!file_exists($segmentFile['file'])) {
                    throw new \RuntimeException("TTS did not create audio for segment {$i}.");
                }

                Log::info("Segment {$i} ready", [
                    'start' => $segmentFile['start'],
                    'end'   => $segmentFile['end'],
                    'text'  => mb_substr($segmentFile['text'], 0, 40),
                ]);
            }

            if (empty($segmentFiles)) {
                throw new \Exception('No valid segments were generated.');
            }

            $mergedAudio = $this->mergeSegmentsToTimeline(
                $segmentFiles,
                $videoDuration,
                $sessionId
            );

            $outputDirectory = dirname($outputPath);
            if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0755, true) && !is_dir($outputDirectory)) {
                throw new \RuntimeException("Unable to create audio output directory: {$outputDirectory}");
            }

            if (!rename($mergedAudio, $outputPath)) {
                throw new \RuntimeException("Unable to move synchronized audio to: {$outputPath}");
            }

            $completed = true;
            Log::info('Synced audio build completed', ['output' => $outputPath]);

            return $outputPath;

        } finally {
            if ($completed) {
                foreach ($segmentFiles as $seg) {
                    if (file_exists($seg['file'])) {
                        unlink($seg['file']);
                    }
                }
            }

            if ($mergedAudio !== null && file_exists($mergedAudio)) {
                unlink($mergedAudio);
            }
        }
    }

    private function mergeSegmentsToTimeline(
        array $segmentFiles,
        float $videoDuration,
        string $sessionId
    ): string {
        $outputPath = "{$this->tempPath}/{$sessionId}_merged.mp3";
        $concatPath = "{$this->tempPath}/{$sessionId}_concat.txt";
        $temporaryFiles = [];
        $concatEntries = [];
        $cursor = 0.0;

        try {
            foreach (array_chunk($segmentFiles, 24) as $groupIndex => $group) {
                $groupStart = (float) $group[0]['start'];
                $groupEnd = (float) end($group)['end'];

                if ($groupStart > $cursor) {
                    $gapPath = "{$this->tempPath}/{$sessionId}_gap_{$groupIndex}.mp3";
                    $this->generateSilence($gapPath, $groupStart - $cursor);
                    $temporaryFiles[] = $gapPath;
                    $concatEntries[] = $gapPath;
                }

                $groupPath = "{$this->tempPath}/{$sessionId}_group_{$groupIndex}.mp3";
                $this->mixSegmentGroup(
                    $group,
                    $groupStart,
                    $groupEnd - $groupStart,
                    $groupPath,
                    $sessionId . '_' . $groupIndex
                );
                $temporaryFiles[] = $groupPath;
                $concatEntries[] = $groupPath;
                $cursor = $groupEnd;
            }

            if ($videoDuration > $cursor) {
                $tailPath = "{$this->tempPath}/{$sessionId}_tail.mp3";
                $this->generateSilence($tailPath, $videoDuration - $cursor);
                $temporaryFiles[] = $tailPath;
                $concatEntries[] = $tailPath;
            }

            $concatContents = implode('', array_map(
                fn ($path) => "file '" . str_replace(["\\", "'"], ["/", "'\\''"], $path) . "'\n",
                $concatEntries
            ));
            if (file_put_contents($concatPath, $concatContents) === false) {
                throw new \RuntimeException('Unable to write FFmpeg audio concat list.');
            }

            $command = sprintf(
                '%s -f concat -safe 0 -i %s -ar 44100 -ac 2 -b:a 128k %s -y',
                env('FFMPEG_PATH', 'ffmpeg'),
                escapeshellarg($concatPath),
                escapeshellarg($outputPath)
            );
            $output = [];
            $returnCode = null;
            exec($command . ' 2>&1', $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($outputPath) || filesize($outputPath) === 0) {
                Log::error('FFmpeg segment concat failed', [
                    'output' => implode("\n", array_slice($output, -10)),
                ]);
                throw new \RuntimeException(
                    'Segment concat failed: ' . implode("\n", array_slice($output, -5))
                );
            }

            return $outputPath;
        } finally {
            foreach ($temporaryFiles as $temporaryFile) {
                if (file_exists($temporaryFile)) {
                    unlink($temporaryFile);
                }
            }
            if (file_exists($concatPath)) {
                unlink($concatPath);
            }
        }
    }

    private function mixSegmentGroup(
        array $segments,
        float $groupStart,
        float $groupDuration,
        string $outputPath,
        string $groupId
    ): void {
        $silencePath = "{$this->tempPath}/{$groupId}_silence.mp3";
        $filterPath = "{$this->tempPath}/{$groupId}_filter.txt";
        $this->generateSilence($silencePath, $groupDuration);

        try {
            $inputArgs = '-i ' . escapeshellarg($silencePath);
            $filterParts = ['[0:a]anull[silence]'];
            $mixLabels = ['[silence]'];

            foreach ($segments as $index => $segment) {
                $inputIndex = $index + 1;
                $delayMs = (int) round(((float) $segment['start'] - $groupStart) * 1000);
                $inputArgs .= ' -i ' . escapeshellarg($segment['file']);
                $filterParts[] = "[{$inputIndex}:a]adelay={$delayMs}|{$delayMs}[s{$index}]";
                $mixLabels[] = "[s{$index}]";
            }

            $inputCount = count($segments) + 1;
            $filter = implode(';', $filterParts) . ';'
                . implode('', $mixLabels)
                . "amix=inputs={$inputCount}:duration=first:dropout_transition=0:normalize=0[out]";
            if (file_put_contents($filterPath, $filter) === false) {
                throw new \RuntimeException('Unable to write FFmpeg audio filter script.');
            }

            $command = sprintf(
                '%s %s -filter_complex_script %s -map "[out]" -ar 44100 -ac 2 -b:a 128k %s -y',
                env('FFMPEG_PATH', 'ffmpeg'),
                $inputArgs,
                escapeshellarg($filterPath),
                escapeshellarg($outputPath)
            );
            $output = [];
            $returnCode = null;
            exec($command . ' 2>&1', $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($outputPath) || filesize($outputPath) === 0) {
                throw new \RuntimeException(
                    'FFmpeg audio group mix failed: ' . implode("\n", array_slice($output, -5))
                );
            }
        } finally {
            foreach ([$silencePath, $filterPath] as $temporaryFile) {
                if (file_exists($temporaryFile)) {
                    unlink($temporaryFile);
                }
            }
        }
    }

    private function generateSilence(string $outputPath, float $duration): void
    {
        $cmd = sprintf(
            '%s -f lavfi -i anullsrc=r=44100:cl=stereo -t %.3f -ar 44100 -ac 2 -b:a 128k %s -y',
            env('FFMPEG_PATH', 'ffmpeg'),
            $duration,
            escapeshellarg($outputPath)
        );

        $output     = [];
        $returnCode = null;
        exec($cmd . " 2>&1", $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputPath)) {
            throw new \Exception('Failed to generate silence: ' . implode("\n", $output));
        }
    }
}