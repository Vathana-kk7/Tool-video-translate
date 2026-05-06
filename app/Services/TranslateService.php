<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranslateService
{
    protected string $apiKey;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = env('GOOGLE_TRANSLATE_API_KEY');
        $this->apiUrl = 'https://translation.googleapis.com/language/translate/v2';
    }

    /**
     * Translate text from Chinese to Khmer
     */
    public function translateToKhmer(string $text): string
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '?key=' . $this->apiKey, [
                'q' => $text,
                'source' => 'zh',
                'target' => 'km',
                'format' => 'text',
            ]);

            if (!$response->successful()) {
                throw new \Exception('Translation API error: ' . $response->body());
            }

            $data = $response->json();
            $translatedText = $data['data']['translations'][0]['translatedText'] ?? '';

            Log::info('Translation completed', [
                'source_lang' => 'zh',
                'target_lang' => 'km',
                'original_length' => strlen($text),
                'translated_length' => strlen($translatedText)
            ]);

            return $translatedText;
        } catch (\Exception $e) {
            Log::error('Translation failed', [
                'error' => $e->getMessage(),
                'text' => substr($text, 0, 100)
            ]);
            throw $e;
        }
    }

    /**
     * Translate multiple sentences
     */
    public function translateBatch(array $sentences): array
    {
        $translated = [];

        foreach ($sentences as $index => $sentence) {
            try {
                $translated[] = [
                    'original' => $sentence,
                    'translated' => $this->translateToKhmer($sentence),
                    'index' => $index,
                ];
            } catch (\Exception $e) {
                Log::error('Failed to translate sentence', [
                    'index' => $index,
                    'sentence' => $sentence,
                    'error' => $e->getMessage()
                ]);
                $translated[] = [
                    'original' => $sentence,
                    'translated' => $sentence, // Fallback to original
                    'index' => $index,
                    'failed' => true,
                ];
            }
        }

        return $translated;
    }

    /**
     * Get language name from code
     */
    public function getLanguageName(string $code): string
    {
        $languages = [
            'zh' => 'Chinese',
            'km' => 'Khmer',
        ];

        return $languages[$code] ?? $code;
    }
}