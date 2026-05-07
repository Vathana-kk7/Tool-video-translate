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
        // optional: clean text
        $text = addslashes($text);

        $cmd = sprintf(
            'python -m edge_tts --text "%s" --voice en-US-AriaNeural --write-media "%s"',
            $text,
            $outputPath
        );

        exec($cmd, $output, $status);

        if ($status !== 0 || !file_exists($outputPath)) {
            throw new \Exception("Edge TTS failed to generate audio");
        }

        Log::info('Edge TTS success', [
            'output' => $outputPath,
            'text_length' => strlen($text),
        ]);

        return $outputPath;

    } catch (\Exception $e) {
        Log::error('TTS failed', [
            'error' => $e->getMessage(),
            'text' => substr($text, 0, 100),
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

        $chunks  = [];
        $words   = explode(' ', $text);
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
        $content .= "file '" . str_replace('\\', '/', $file) . "'\n";
    }

    file_put_contents($listFile, $content);

    $ffmpeg = env('FFMPEG_PATH', 'ffmpeg');

    // ✅ Windows compatible (លប់ 2>/dev/null ចោល)
    $cmd = sprintf(
        '"%s" -f concat -safe 0 -i "%s" -c copy "%s" -y',
        $ffmpeg,
        $listFile,
        $outputPath
    );

    exec($cmd, $output, $returnCode);

    if (file_exists($listFile)) unlink($listFile);

    if ($returnCode !== 0 || !file_exists($outputPath)) {
        throw new \Exception('Failed to merge audio files');
    }
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
