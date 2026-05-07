<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranslateService
{
    public function translateToKhmer(string $text): string
    {
        try {
            // ✅ Split if text too long (MyMemory limit is 500 chars)
            if (strlen($text) > 450) {
                return $this->translateLongText($text);
            }

            $response = Http::timeout(30)->get('https://api.mymemory.translated.net/get', [
                'q'        => $text,
                'langpair' => 'zh|km',
            ]);

            if (!$response->successful()) {
                throw new \Exception('Translation API error: ' . $response->body());
            }

            $data           = $response->json();
            $translatedText = $data['responseData']['translatedText'] ?? $text;

            // ✅ Check if translation actually worked
            if ($translatedText === $text || empty($translatedText)) {
                Log::warning('Translation may have failed, returned original text', [
                    'text' => substr($text, 0, 100),
                ]);
            }

            Log::info('Translation completed', [
                'original_length'   => strlen($text),
                'translated_length' => strlen($translatedText),
            ]);

            return $translatedText;

        } catch (\Exception $e) {
            Log::error('Translation failed', [
                'error' => $e->getMessage(),
                'text'  => substr($text, 0, 100),
            ]);
            throw $e;
        }
    }

    // ✅ New method to handle long text
    private function translateLongText(string $text): string
    {
        // Split by sentences first
        $sentences = preg_split('/(?<=[。！？；\.\!\?])/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $chunks    = [];
        $current   = '';

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
            $translated[] = $this->translateToKhmer(trim($chunk));
            sleep(1); // ✅ Avoid rate limiting
        }

        return implode(' ', $translated);
    }

    public function translateBatch(array $sentences): array
    {
        $translated = [];

        foreach ($sentences as $index => $sentence) {
            try {
                $translated[] = [
                    'original'   => $sentence,
                    'translated' => $this->translateToKhmer($sentence),
                    'index'      => $index,
                ];

                // ✅ Small delay between requests
                usleep(500000); // 0.5 seconds

            } catch (\Exception $e) {
                Log::warning('Batch translation failed for sentence', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);

                $translated[] = [
                    'original'   => $sentence,
                    'translated' => $sentence, // fallback to original
                    'index'      => $index,
                    'failed'     => true,
                ];
            }
        }

        return $translated;
    }

    public function getLanguageName(string $code): string
    {
        return ['zh' => 'Chinese', 'km' => 'Khmer'][$code] ?? $code;
    }
}
