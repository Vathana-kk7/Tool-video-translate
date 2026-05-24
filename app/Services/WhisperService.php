<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhisperService
{
    // កែប្រែទៅជា ?string ដើម្បីអនុញ្ញាតឱ្យតម្លៃ null ក្នុងករណីមិនទាន់មាន Key
    protected ?string $apiKey;
    protected string $apiUrl;

    public function __construct()
    {
        // ទាញយក Key ពី config ឬ .env
        $this->apiKey = config('services.groq.key') ?? env('GROQ_API_KEY');
        $this->apiUrl = 'https://api.groq.com/openai/v1/audio/transcriptions';

        // ត្រួតពិនិត្យមើលថាតើមាន Key ឬអត់ ដើម្បីការពារ Error ពេលដំណើរការ
        if (empty($this->apiKey)) {
            Log::error('Groq API Key មិនត្រូវបានកំណត់ក្នុង .env ទេ។ សូមពិនិត្យមើល GROQ_API_KEY។');
        }
    }

    public function transcribe(string $audioFilePath, string $language = 'zh'): array
    {
        try {
            if (!file_exists($audioFilePath) || !is_readable($audioFilePath)) {
                throw new \Exception("រកមិនឃើញឯកសារសំឡេង ឬមិនអាចអានបានឡើយ: {$audioFilePath}");
            }

            // ឆែកមើលទំហំ File (Groq កំណត់ត្រឹម 25MB)
            $fileSizeBytes = filesize($audioFilePath);
            $fileSizeMB = $fileSizeBytes / 1024 / 1024;

            if ($fileSizeMB > 24) {
                throw new \Exception("ឯកសារធំពេក: {$fileSizeMB}MB។ Groq API អនុញ្ញាតត្រឹម 24MB ប៉ុណ្ណោះ។");
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $audioFilePath);
            finfo_close($finfo);

            $allowedAudioTypes = [
                'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/ogg',
                'audio/flac', 'audio/mp4', 'audio/x-m4a', 'audio/aac',
                'application/octet-stream'
            ];

            if (!in_array($mimeType, $allowedAudioTypes) && strpos($mimeType, 'audio/') !== 0) {
                throw new \Exception("ប្រភេទឯកសារមិនត្រឹមត្រូវ: {$mimeType}។");
            }

            // បង្កើន timeout ដល់ 300 វិនាទី (៥ នាទី) ដើម្បីការពារបញ្ហា Timeout
            $response = Http::timeout(600)
    ->withHeaders([
        'Authorization' => 'Bearer ' . $this->apiKey,
    ])
    ->attach(
        'file',
        fopen($audioFilePath, 'r'), // ប្រើ fopen ដើម្បីផ្ញើជា stream
        basename($audioFilePath)    // ប្រើឈ្មោះ file ពិតប្រាកដ
    )
    ->post($this->apiUrl, [
        'model'           => 'whisper-large-v3-turbo',
        'language'        => $language,
        'response_format' => 'verbose_json',
        'temperature'     => 0,
    ]);

            if (!$response->successful()) {
                throw new \Exception('Whisper API Error: ' . $response->body());
            }

            $result = $response->json();

            Log::info('ការបម្លែងសំឡេងត្រូវបានបញ្ចប់', [
                'audio_file'  => $audioFilePath,
                'text_length' => strlen($result['text'] ?? ''),
            ]);

            return [
                'text'     => $result['text'] ?? '',
                'segments' => $result['segments'] ?? [],
                'language' => $result['language'] ?? $language,
            ];

        } catch (\Exception $e) {
            Log::error('Whisper Transcription Fail: ' . $e->getMessage());
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
            'segments'  => $result['segments'],
        ];
    }

    private function splitIntoSentences(string $text): array
    {
        $pattern   = '/(?<=[。！？；])/u';
        $sentences = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter(array_map('trim', $sentences)));
    }
}
