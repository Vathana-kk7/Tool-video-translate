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
        $segmentFiles = [];

        try {
            Log::info('Starting synced audio build', [
                'segment_count'  => count($segments),
                'video_duration' => $videoDuration,
            ]);

            foreach ($segments as $i => $segment) {
                $start    = (float) ($segment['start'] ?? 0);
                $end      = (float) ($segment['end'] ?? 0);
                $duration = $end - $start;

                // ✅ ប្រើ translated text ពី segment — មិន translate ម្តងទៀតទេ
                $khmerText = trim($segment['translated'] ?? $segment['text'] ?? '');

                if (empty($khmerText) || $duration <= 0) {
                    Log::warning("Segment {$i} skipped", [
                        'reason'   => empty($khmerText) ? 'empty text' : 'invalid duration',
                        'duration' => $duration,
                    ]);
                    continue;
                }

                $segFile = "{$this->tempPath}/{$sessionId}_seg_{$i}.mp3";
                $this->tts->generateSpeechWithTiming($khmerText, $segFile, $duration);

                if (!file_exists($segFile)) {
                    Log::warning("Segment {$i} file not created, skipping.");
                    continue;
                }

                $segmentFiles[] = [
                    'file'     => $segFile,
                    'start'    => $start,
                    'end'      => $end,
                    'duration' => $duration,
                    'text'     => $khmerText,
                ];

                Log::info("Segment {$i} ready", [
                    'start' => $start,
                    'end'   => $end,
                    'text'  => mb_substr($khmerText, 0, 40),
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

            rename($mergedAudio, $outputPath);

            Log::info('Synced audio build completed', ['output' => $outputPath]);

            return $outputPath;

        } finally {
            foreach ($segmentFiles as $seg) {
                if (file_exists($seg['file'])) {
                    unlink($seg['file']);
                }
            }
        }
    }

    private function mergeSegmentsToTimeline(
        array $segmentFiles,
        float $videoDuration,
        string $sessionId
    ): string {
        $outputPath = "{$this->tempPath}/{$sessionId}_merged.mp3";
        $ffmpeg     = env('FFMPEG_PATH', 'ffmpeg');

        // Silence ជា input [0] — base timeline
        $silencePath = "{$this->tempPath}/{$sessionId}_silence.mp3";
        $this->generateSilence($silencePath, $videoDuration);

        $inputArgs   = '-i ' . escapeshellarg($silencePath);
        $filterParts = ['[0]anull[silence]'];
        $mixLabels   = ['[silence]'];

        foreach ($segmentFiles as $i => $seg) {
            $inputIndex = $i + 1;
            $delayMs    = (int) round($seg['start'] * 1000);

            $inputArgs    .= ' -i ' . escapeshellarg($seg['file']);
            $filterParts[] = "[{$inputIndex}]adelay={$delayMs}|{$delayMs}[s{$i}]";
            $mixLabels[]   = "[s{$i}]";
        }

        $totalInputs   = count($segmentFiles) + 1;
        $filterComplex = implode(';', $filterParts) . ';'
            . implode('', $mixLabels)
            . "amix=inputs={$totalInputs}:duration=first:dropout_transition=0[out]";

        $cmd = sprintf(
            '%s %s -filter_complex %s -map "[out]" -ar 44100 -ac 2 -b:a 128k %s -y',
            $ffmpeg,
            $inputArgs,
            escapeshellarg($filterComplex),
            escapeshellarg($outputPath)
        );

        $output     = [];
        $returnCode = null;
        exec($cmd . " 2>&1", $output, $returnCode);

        if (file_exists($silencePath)) {
            unlink($silencePath);
        }

        if ($returnCode !== 0) {
            Log::error('FFmpeg segment merge failed', [
                'output' => implode("\n", array_slice($output, -10)),
            ]);
            throw new \Exception('Segment merge failed: ' . implode("\n", array_slice($output, -5)));
        }

        if (!file_exists($outputPath)) {
            throw new \Exception('Merged audio file was not created.');
        }

        return $outputPath;
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