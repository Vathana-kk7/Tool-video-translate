<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BackgroundAudioSeparationService
{
    public function extractBackgroundAudio(string $videoFilePath): string
    {
        $workPath = storage_path('app/temp_audio_separation/' . uniqid('separation_', true));
        $sourcePath = $workPath . DIRECTORY_SEPARATOR . 'source.wav';
        $stemsPath = $workPath . DIRECTORY_SEPARATOR . 'stems';
        $outputPath = storage_path('app/temp_audio_separation/' . uniqid('background_', true) . '.wav');

        if (!mkdir($workPath, 0755, true) && !is_dir($workPath)) {
            throw new \RuntimeException("Unable to create audio separation directory: {$workPath}");
        }

        try {
            $this->runCommand(sprintf(
                '%s -i %s -vn -ac 2 -ar 44100 -c:a pcm_s16le %s -y',
                env('FFMPEG_PATH', 'ffmpeg'),
                escapeshellarg($videoFilePath),
                escapeshellarg($sourcePath)
            ), 'Unable to extract audio for separation');

            if (!mkdir($stemsPath, 0755, true) && !is_dir($stemsPath)) {
                throw new \RuntimeException("Unable to create Demucs output directory: {$stemsPath}");
            }

            $model = (string) env('DEMUCS_MODEL', 'htdemucs');
            $python = (string) env('DEMUCS_PYTHON_PATH', 'python');
            $this->runCommand(sprintf(
                '%s -m demucs --two-stems vocals -n %s -o %s %s',
                escapeshellarg($python),
                escapeshellarg($model),
                escapeshellarg($stemsPath),
                escapeshellarg($sourcePath)
            ), 'Demucs failed to separate vocals from background audio');

            $separatedPath = $stemsPath . DIRECTORY_SEPARATOR . $model
                . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'no_vocals.wav';
            if (!is_file($separatedPath) || filesize($separatedPath) === 0) {
                throw new \RuntimeException("Demucs did not create the expected background stem: {$separatedPath}");
            }

            if (!copy($separatedPath, $outputPath)) {
                throw new \RuntimeException('Unable to preserve the separated background audio.');
            }

            return $outputPath;
        } catch (\Throwable $e) {
            Log::error('Background audio separation failed', [
                'video_file' => $videoFilePath,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            $this->removeDirectory($workPath);
        }
    }

    private function runCommand(string $command, string $failureMessage): void
    {
        $output = [];
        $returnCode = null;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException(
                $failureMessage . ': ' . implode("\n", array_slice($output, -10))
            );
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}
