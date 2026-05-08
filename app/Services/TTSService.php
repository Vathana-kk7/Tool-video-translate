<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TTSService
{
    /**
     * Generate speech
     */
    public function generateSpeech(string $text, string $outputPath): string
    {
        try {
            $apiKey = config('services.tts.key', env('TTS_API_KEY'));
            $url    = config('services.tts.url', env('TTS_API_URL'));
            $voice  = config('services.tts.voice', env('TTS_VOICE', 'km-KH-SreymomNeural'));

            if (!$apiKey || !$url) {
                throw new \Exception('Missing TTS config (API_KEY or URL)');
            }

            $chunks = $this->splitText($text, 900);
            $files = [];
foreach ($chunks as $i => $chunk) {
    $file = $outputPath . "_{$i}.mp3";

    if ($i > 0) {
        sleep(35); // បន្ថែមពី 25 → 35 វិនាទី
    }

    $response = $this->makeRequest($url, $apiKey, $voice, $chunk);

    if (!$response->successful()) {
        throw new \Exception('TTS failed: ' . $response->body());
    }

    file_put_contents($file, $response->body());
    $files[] = $file;
}

            return $this->mergeFiles($files, $outputPath);

        } catch (\Exception $e) {
            Log::error('TTS Error', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Make HTTP request to TTS API
     */
    private function makeRequest(string $url, string $apiKey, string $voice, string $text, int $attempt = 1)
{
    $response = Http::timeout(60)
        ->withHeaders([
            'X-API-Key'    => $apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'audio/mpeg',
        ])
        ->post($url, [
            'text'  => $text,
            'voice' => $voice,
            'rate'  => '+0%',
            'pitch' => '+0Hz',
        ]);

    // Retry សម្រាប់ rate limit និង concurrent limit
    if (in_array($response->status(), [429, 503]) && $attempt <= 5) {
        $waitTime = $response->json('detail.retry_after') ?? 60;
        Log::warning("TTS limit hit (attempt {$attempt}), waiting {$waitTime}s...");
        sleep($waitTime + 5);
        return $this->makeRequest($url, $apiKey, $voice, $text, $attempt + 1);
    }

    return $response;
}
    private function splitText(string $text, int $limit): array
    {
        $words = preg_split('/\s+/u', $text);
        $chunks = [];
        $current = '';

        foreach ($words as $word) {
            if (mb_strlen(trim($current . ' ' . $word)) > $limit) {
                if ($current !== '') {
                    $chunks[] = trim($current);
                }
                $current = $word;
            } else {
                $current .= ' ' . $word;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    /**
     * Merge audio files with ffmpeg
     */
    private function mergeFiles(array $files, string $outputPath): string
    {
        if (count($files) === 1) {
            rename($files[0], $outputPath);
            return $outputPath;
        }

        $list = $outputPath . '_list.txt';

        $content = '';
        foreach ($files as $f) {
            $content .= "file '" . str_replace('\\', '/', $f) . "'\n";
        }

        file_put_contents($list, $content);

        $ffmpeg = env('FFMPEG_PATH', 'ffmpeg');
        $cmd = "\"$ffmpeg\" -f concat -safe 0 -i \"$list\" -c copy \"$outputPath\" -y";

        $result = null;
        exec($cmd, $output, $result);

        foreach ($files as $f) {
            if (file_exists($f)) unlink($f);
        }

        if (file_exists($list)) unlink($list);

        if ($result !== 0) {
            throw new \Exception('FFmpeg merge failed with code: ' . $result);
        }

        return $outputPath;
    }
}
