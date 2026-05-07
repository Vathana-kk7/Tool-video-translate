<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranslateService
{
    public function translateToKhmer(string $text): string
    {
        try {
            $response = Http::get('https://api.mymemory.translated.net/get', [
                'q'        => $text,
                'langpair' => 'zh|km',
            ]);

            if (!$response->successful()) {
                throw new \Exception('Translation API error: ' . $response->body());
            }

            $data = $response->json();
            $translatedText = $data['responseData']['translatedText'] ?? $text;

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
            } catch (\Exception $e) {
                $translated[] = [
                    'original'   => $sentence,
                    'translated' => $sentence,
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
