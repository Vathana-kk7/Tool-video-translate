<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranslateService
{
    private string $apiUrl = 'https://api.mymemory.translated.net/get';

    /**
     * Main translate function
     */
    public function translateToKhmer(string $text): string
    {
        try {
            if (strlen($text) > 450) {
                return $this->translateLongText($text);
            }

            return $this->translateChunk($text);

        } catch (\Exception $e) {
            Log::error('Translate failed', [
                'error' => $e->getMessage()
            ]);

            return $text; // fallback
        }
    }

    /**
     * Translate single chunk
     */
    private function translateChunk(string $text): string
    {
        $response = Http::timeout(30)->get($this->apiUrl, [
            'q'        => $text,
            'langpair' => 'zh|km',
        ]);

        if (!$response->successful()) {
            return $text;
        }

        return $response->json()['responseData']['translatedText'] ?? $text;
    }

    /**
     * Handle long text safely
     */
    private function translateLongText(string $text): string
    {
        $sentences = preg_split('/(?<=[。！？；\.\!\?])/u', $text);

        $chunks = [];
        $current = '';

        foreach ($sentences as $sentence) {
            if (strlen($current . $sentence) <= 450) {
                $current .= $sentence;
            } else {
                if ($current) $chunks[] = $current;
                $current = $sentence;
            }
        }

        if ($current) $chunks[] = $current;

        $translated = [];

        foreach ($chunks as $chunk) {
            $translated[] = $this->translateChunk(trim($chunk));
            usleep(500000); // avoid limit
        }

        return implode(' ', $translated);
    }

    /**
     * Batch translate
     */
    public function translateBatch(array $sentences): array
    {
        $result = [];

        foreach ($sentences as $i => $sentence) {
            $result[] = [
                'index'      => $i,
                'original'   => $sentence,
                'translated' => $this->translateToKhmer($sentence),
            ];

            usleep(300000);
        }

        return $result;
    }
}
