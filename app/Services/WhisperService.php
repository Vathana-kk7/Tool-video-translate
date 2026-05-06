<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhisperService
{
    protected string $apiKey;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY');
        $this->apiUrl = 'https://api.openai.com/v1/audio/transcriptions';
    }

    /**
     * Transcribe audio using OpenAI Whisper API
     */
    public function transcribe(string $audioFilePath, string $language = 'zh'): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->attach(
                'file',
                file_get_contents($audioFilePath),
                'audio.mp3'
            )->withOptions([
                'multipart' => [
                    'model' => 'whisper-1',
                    'language' => $language,
                    'response_format' => 'verbose_json',
                    'temperature' => 0,
                ],
            ])->post($this->apiUrl);

            if (!$response->successful()) {
                throw new \Exception('Whisper API error: ' . $response->body());
            }

            $result = $response->json();

            Log::info('Whisper transcription completed', [
                'audio_file' => $audioFilePath,
                'language' => $language,
                'text_length' => strlen($result['text'] ?? '')
            ]);

            return [
                'text' => $result['text'] ?? '',
                'segments' => $result['segments'] ?? [],
                'language' => $result['language'] ?? $language,
            ];
        } catch (\Exception $e) {
            Log::error('Whisper transcription failed', [
                'error' => $e->getMessage(),
                'audio_file' => $audioFilePath
            ]);
            throw $e;
        }
    }

    /**
     * Transcribe and split into sentences
     */
    public function transcribeWithSegments(string $audioFilePath): array
    {
        $result = $this->transcribe($audioFilePath, 'zh');

        // Split text into sentences for better translation
        $sentences = $this->splitIntoSentences($result['text']);

        return [
            'full_text' => $result['text'],
            'sentences' => $sentences,
            'segments' => $result['segments'],
        ];
    }

    /**
     * Split Chinese text into sentences
     */
    private function splitIntoSentences(string $text): array
    {
        // Split by Chinese punctuation marks
        $pattern = '/(?<=[。！？；])/u';
        $sentences = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(array_map('trim', $sentences)));
    }
}