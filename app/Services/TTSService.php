<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TTSService
{
    /**
     * Generate Khmer speech using Google Translate TTS (FREE)
     */
    public function generateSpeech(string $text, string $outputPath): string
    {
        try {
            // Split text into chunks (Google TTS max 200 chars)
            $chunks = $this->splitText($text, 200);
            $tempFiles = [];

            foreach ($chunks as $index => $chunk) {
                $tempFile = $outputPath . '_part_' . $index . '.mp3';

                $url = 'https://translate.google.com/translate_tts';
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer'    => 'https://translate.google.com/',
                ])->get($url, [
                    'ie'     => 'UTF-8',
                    'q'      => $chunk,
                    'tl'     => 'km',
                    'client' => 'tw-ob',
                ]);

                if (!$response->successful()) {
                    throw new \Exception('Google TTS error: ' . $response->status());
                }

                file_put_contents($tempFile, $response->body());
                $tempFiles[] = $tempFile;

                // Small delay to avoid rate limiting
                usleep(300000); // 0.3 seconds
            }

            // Merge all parts if multiple chunks
            if (count($tempFiles) === 1) {
                rename($tempFiles[0], $outputPath);
            } else {
                $this->mergeAudioFiles($tempFiles, $outputPath);
                foreach ($tempFiles as $tempFile) {
                    if (file_exists($tempFile)) unlink($tempFile);
                }
            }

            Log::info('TTS generation completed', [
                'text_length' => strlen($text),
                'output_file' => $outputPath,
                'chunks'      => count($chunks),
            ]);

            return $outputPath;

        } catch (\Exception $e) {
            Log::error('TTS generation failed', [
                'error' => $e->getMessage(),
                'text'  => substr($text, 0, 100),
            ]);
            throw $e;
        }
    }

    /**
     * Split text into chunks
     */
    private function splitText(string $text, int $maxLength): array
    {
        if (strlen($text) <= $maxLength) {
            return [$text];
        }

        $chunks = [];
        $words  = explode(' ', $text);
        $current = '';

        foreach ($words as $word) {
            if (strlen($current . ' ' . $word) <= $maxLength) {
                $current .= ($current ? ' ' : '') . $word;
            } else {
                if ($current) $chunks[] = $current;
                $current = $word;
            }
        }

        if ($current) $chunks[] = $current;

        return $chunks;
    }

    /**
     * Merge multiple mp3 files using FFmpeg
     */
    private function mergeAudioFiles(array $files, string $outputPath): void
    {
        $listFile = $outputPath . '_list.txt';
        $content  = '';

        foreach ($files as $file) {
            $content .= "file '" . $file . "'\n";
        }

        file_put_contents($listFile, $content);

        $cmd = sprintf(
            '%s -f concat -safe 0 -i %s -c copy %s -y 2>/dev/null',
            env('FFMPEG_PATH', 'ffmpeg'),
            escapeshellarg($listFile),
            escapeshellarg($outputPath)
        );

        exec($cmd);
        if (file_exists($listFile)) unlink($listFile);
    }

    /**
     * Batch generate TTS segments
     */
    public function generateBatchTTS(array $segments, string $outputDir): array
    {
        $audioFiles = [];

        foreach ($segments as $index => $segment) {
            $outputFile = $outputDir . '/segment_' . str_pad($index, 4, '0', STR_PAD_LEFT) . '.mp3';

            try {
                $this->generateSpeech($segment['translated'], $outputFile);
                $audioFiles[] = [
                    'file'       => $outputFile,
                    'start_time' => $segment['start_time'] ?? 0,
                    'end_time'   => $segment['end_time'] ?? 0,
                    'text'       => $segment['translated'],
                ];
            } catch (\Exception $e) {
                Log::error('Failed to generate TTS for segment', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $audioFiles;
    }
}
