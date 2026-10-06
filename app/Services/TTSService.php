<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TTSService
{
    /**
     * បម្លែងអត្ថបទទៅជាសំឡេងដោយប្រើ Edge TTS
     */
    public function generateSpeech(string $text, string $outputPath): string
    {
        try {
            if (trim($text) === '') {
                throw new \InvalidArgumentException('Speech text cannot be empty.');
            }

            $scriptPath = base_path('tts_edge.py');
            $dir = dirname($outputPath);
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }

            $python = env('PYTHON_PATH', PHP_OS_FAMILY === 'Windows' ? 'py' : 'python3');
            $command = escapeshellarg($python) . ' ' . escapeshellarg($scriptPath) . ' '
                . escapeshellarg($text) . ' ' . escapeshellarg($outputPath);
            $output = [];
            $resultCode = null;
            exec($command . " 2>&1", $output, $resultCode);

            if ($resultCode !== 0) {
                $errorMessage = implode("\n", $output);
                Log::error("Edge TTS Detail Error: " . $errorMessage);
                throw new \Exception("Edge TTS failed: " . $errorMessage);
            }

            if (!file_exists($outputPath) || filesize($outputPath) === 0) {
                throw new \Exception("Audio file was not created at: " . $outputPath);
            }

            return $outputPath;

        } catch (\Exception $e) {
            Log::error('TTS Error (Edge TTS)', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function generateSpeechWithTiming(string $text, string $outputPath, float $targetDuration): string
    {
        if ($targetDuration <= 0) {
            throw new \InvalidArgumentException('Target speech duration must be greater than zero.');
        }

        $sourcePath = $outputPath . '.source.mp3';

        try {
            $this->generateSpeech($text, $sourcePath);
            return $this->fitSpeechToDuration($sourcePath, $outputPath, $targetDuration);
        } finally {
            if (file_exists($sourcePath)) {
                unlink($sourcePath);
            }
        }
    }

    public function generateSpeechWithTimingBatch(array $segments): array
    {
        if (!$segments) {
            return [];
        }

        $manifestPath = storage_path('app/tts_batch_' . uniqid('', true) . '.json');
        $jobs = [];

        foreach ($segments as $segment) {
            $text = trim((string) ($segment['text'] ?? ''));
            $outputPath = (string) ($segment['output_path'] ?? '');
            $duration = (float) ($segment['duration'] ?? 0);

            if ($text === '' || $outputPath === '' || $duration <= 0) {
                throw new \InvalidArgumentException('Every TTS batch segment needs text, output_path, and a positive duration.');
            }

            $directory = dirname($outputPath);
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException("Unable to create TTS output directory: {$directory}");
            }

            $jobs[] = [
                'text' => $text,
                'source_path' => $outputPath . '.source.mp3',
                'output_path' => $outputPath,
                'duration' => $duration,
            ];
        }

        $jobsToGenerate = array_values(array_filter(
            $jobs,
            static fn ($job) => !file_exists($job['output_path']) || filesize($job['output_path']) === 0
        ));
        $manifest = array_map(static fn ($job) => [
            'text' => $job['text'],
            'output_file' => $job['source_path'],
        ], $jobsToGenerate);

        try {
            if ($jobsToGenerate) {
                if (file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE)) === false) {
                    throw new \RuntimeException('Unable to write TTS batch manifest.');
                }

                $scriptPath = base_path('tts_edge.py');
                $python = env('PYTHON_PATH', PHP_OS_FAMILY === 'Windows' ? 'py' : 'python3');
                $command = escapeshellarg($python) . ' ' . escapeshellarg($scriptPath)
                    . ' --batch ' . escapeshellarg($manifestPath);
                $output = [];
                $returnCode = null;
                exec($command . ' 2>&1', $output, $returnCode);

                if ($returnCode !== 0) {
                    throw new \RuntimeException(
                        'Edge TTS batch failed: ' . implode("\n", array_slice($output, -5))
                    );
                }
            }

            foreach ($jobs as $job) {
                if (file_exists($job['output_path']) && filesize($job['output_path']) > 0) {
                    continue;
                }

                if (!file_exists($job['source_path']) || filesize($job['source_path']) === 0) {
                    throw new \RuntimeException('Edge TTS batch did not create all requested audio files.');
                }
                $this->fitSpeechToDuration($job['source_path'], $job['output_path'], $job['duration']);
            }

            return array_column($jobs, 'output_path');
        } finally {
            if (file_exists($manifestPath)) {
                unlink($manifestPath);
            }
            foreach ($jobs as $job) {
                if (file_exists($job['source_path'])) {
                    unlink($job['source_path']);
                }
            }
        }
    }

    private function fitSpeechToDuration(string $sourcePath, string $outputPath, float $targetDuration): string
    {
        $sourceDuration = $this->getAudioDuration($sourcePath);

        if ($sourceDuration <= 0) {
            throw new \RuntimeException('Unable to determine generated speech duration.');
        }

        $speed = max(1, $sourceDuration / $targetDuration);
        $filters = [];
        while ($speed > 2) {
            $filters[] = 'atempo=2';
            $speed /= 2;
        }
        $filters[] = 'atempo=' . rtrim(rtrim(sprintf('%.5F', $speed), '0'), '.');

        $command = sprintf(
            '%s -i %s -filter:a %s -t %.3F -ar 44100 -ac 2 -b:a 128k %s -y',
            env('FFMPEG_PATH', 'ffmpeg'),
            escapeshellarg($sourcePath),
            escapeshellarg(implode(',', $filters)),
            $targetDuration,
            escapeshellarg($outputPath)
        );
        $output = [];
        $returnCode = null;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputPath) || filesize($outputPath) === 0) {
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }

            throw new \RuntimeException(
                'Failed to fit generated speech to its segment: ' . implode("\n", array_slice($output, -5))
            );
        }

        return $outputPath;
    }

    private function getAudioDuration(string $audioPath): float
    {
        $ffprobe = env('FFPROBE_PATH', 'ffprobe');
        $command = sprintf(
            '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
            $ffprobe,
            escapeshellarg($audioPath)
        );
        $output = [];
        $returnCode = null;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0 || !isset($output[0]) || !is_numeric(trim($output[0]))) {
            throw new \RuntimeException('Unable to read generated speech duration: ' . implode("\n", $output));
        }

        return (float) trim($output[0]);
    }
}
