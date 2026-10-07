<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhisperService
{
    private const MAX_UPLOAD_BYTES = 24 * 1024 * 1024;
    private const TRANSCRIPTION_CHUNK_SECONDS = 1200;

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

            // Keep the upload below the provider's 25 MB hard limit.
            $fileSizeBytes = filesize($audioFilePath);
            if ($fileSizeBytes > self::MAX_UPLOAD_BYTES) {
                throw new \RuntimeException('Audio chunk exceeds Groq transcription upload limit.');
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

            $audioStream = fopen($audioFilePath, 'r');
            if ($audioStream === false) {
                throw new \RuntimeException("Unable to open audio file for transcription: {$audioFilePath}");
            }

            try {
                $response = Http::timeout(600)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->apiKey,
                    ])
                    ->attach('file', $audioStream, basename($audioFilePath))
                    ->post($this->apiUrl, [
                        'model' => 'whisper-large-v3-turbo',
                        'language' => $language,
                        'response_format' => 'verbose_json',
                        'temperature' => 0,
                    ]);
            } finally {
                fclose($audioStream);
            }

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
        if (filesize($audioFilePath) > self::MAX_UPLOAD_BYTES) {
            return $this->transcribeInChunks($audioFilePath);
        }

        $result = $this->transcribe($audioFilePath, 'zh');
        $sentences = $this->splitIntoSentences($result['text']);

        return [
            'full_text' => $result['text'],
            'sentences' => $sentences,
            'segments'  => $result['segments'],
        ];
    }

    private function transcribeInChunks(string $audioFilePath): array
    {
        $chunkDirectory = storage_path('app/temp_transcriptions/' . uniqid('whisper_', true));
        if (!mkdir($chunkDirectory, 0755, true) && !is_dir($chunkDirectory)) {
            throw new \RuntimeException("Unable to create transcription chunk directory: {$chunkDirectory}");
        }

        try {
            $audioDuration = $this->getAudioDuration($audioFilePath);
            $chunks = [];
            for ($start = 0.0, $index = 0; $start < $audioDuration; $start += self::TRANSCRIPTION_CHUNK_SECONDS, $index++) {
                $chunkPath = $chunkDirectory . DIRECTORY_SEPARATOR . sprintf('chunk_%03d.mp3', $index);
                $chunkDuration = min(self::TRANSCRIPTION_CHUNK_SECONDS, $audioDuration - $start);
                $command = sprintf(
                    '%s -ss %.3f -i %s -t %.3f -map 0:a:0 -c copy %s -y',
                    env('FFMPEG_PATH', 'ffmpeg'),
                    $start,
                    escapeshellarg($audioFilePath),
                    $chunkDuration,
                    escapeshellarg($chunkPath)
                );
                $output = [];
                $returnCode = null;
                exec($command . ' 2>&1', $output, $returnCode);

                if ($returnCode !== 0 || !is_file($chunkPath) || filesize($chunkPath) === 0) {
                    throw new \RuntimeException(
                        'Unable to create audio transcription chunk: ' . implode("\n", array_slice($output, -10))
                    );
                }

                $chunks[] = ['path' => $chunkPath, 'start' => $start];
            }

            if (count($chunks) < 2) {
                throw new \RuntimeException('Audio exceeded the transcription upload limit but could not be split.');
            }

            $fullText = '';
            $segments = [];

            foreach ($chunks as $chunk) {
                $chunkPath = $chunk['path'];
                $chunkSize = filesize($chunkPath);
                if ($chunkSize === false || $chunkSize > self::MAX_UPLOAD_BYTES) {
                    throw new \RuntimeException('A split audio chunk still exceeds Groq transcription upload limit.');
                }

                $result = $this->transcribe($chunkPath, 'zh');
                $fullText .= $result['text'] ?? '';

                foreach ($result['segments'] ?? [] as $segment) {
                    $segments[] = array_merge($segment, [
                        'start' => (float) ($segment['start'] ?? 0) + $chunk['start'],
                        'end' => (float) ($segment['end'] ?? 0) + $chunk['start'],
                    ]);
                }
            }

            Log::info('Long audio transcription completed in chunks', [
                'audio_file' => $audioFilePath,
                'chunk_count' => count($chunks),
                'duration_seconds' => round($audioDuration, 2),
            ]);

            return [
                'full_text' => $fullText,
                'sentences' => $this->splitIntoSentences($fullText),
                'segments' => $segments,
            ];
        } finally {
            $this->removeDirectory($chunkDirectory);
        }
    }

    private function getAudioDuration(string $audioFilePath): float
    {
        $command = sprintf(
            '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
            env('FFPROBE_PATH', 'ffprobe'),
            escapeshellarg($audioFilePath)
        );
        $output = [];
        $returnCode = null;
        exec($command . ' 2>&1', $output, $returnCode);

        $duration = $returnCode === 0 ? (float) ($output[0] ?? 0) : 0.0;
        if ($duration <= 0) {
            throw new \RuntimeException(
                'Unable to determine transcription chunk duration: ' . implode("\n", $output)
            );
        }

        return $duration;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }

    private function splitIntoSentences(string $text): array
    {
        $pattern   = '/(?<=[。！？；])/u';
        $sentences = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter(array_map('trim', $sentences)));
    }
}
