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
        $this->apiKey = env('GROQ_API_KEY'); // ✅ Groq
        $this->apiUrl = 'https://api.groq.com/openai/v1/audio/transcriptions'; // ✅ Groq
    }

    public function transcribe(string $audioFilePath, string $language = 'zh'): array
    {
        try {
            // Validate that the file is an audio file before sending to API
            if (!file_exists($audioFilePath) || !is_readable($audioFilePath)) {
                throw new \Exception("Audio file not found or not readable: {$audioFilePath}");
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $audioFilePath);
            finfo_close($finfo);

            // Allow common audio MIME types; some files may be detected as application/octet-stream
            $allowedAudioTypes = [
                'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/ogg',
                'audio/flac', 'audio/mp4', 'audio/x-m4a', 'audio/aac',
                'application/octet-stream' // fallback for unknown binary audio
            ];

            if (!in_array($mimeType, $allowedAudioTypes) && strpos($mimeType, 'audio/') !== 0) {
                throw new \Exception("Invalid file type: {$mimeType}. Only audio files are supported for transcription.");
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->attach(
                'file',
                file_get_contents($audioFilePath),
                'audio.mp3'
            )->post($this->apiUrl, [
                'model' => 'whisper-large-v3-turbo', // ✅ Groq model
                'language' => $language,
                'response_format' => 'verbose_json',
                'temperature' => 0,
            ]);

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

    public function transcribeWithSegments(string $audioFilePath): array
    {
        $result = $this->transcribe($audioFilePath, 'zh');

        $sentences = $this->splitIntoSentences($result['text']);

        return [
            'full_text' => $result['text'],
            'sentences' => $sentences,
            'segments' => $result['segments'],
        ];
    }

    private function splitIntoSentences(string $text): array
    {
        $pattern = '/(?<=[。！？；])/u';
        $sentences = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(array_map('trim', $sentences)));
    }
}
