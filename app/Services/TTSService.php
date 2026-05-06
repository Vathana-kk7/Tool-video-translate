<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TTSService
{
    protected string $apiKey;
    protected string $region;
    protected string $endpoint;

    public function __construct()
    {
        $this->apiKey = env('AZURE_TTS_KEY');
        $this->region = env('AZURE_TTS_REGION', 'eastasia');
        $this->endpoint = sprintf(
            'https://%s.tts.speech.microsoft.com/cognitiveservices/v1',
            $this->region
        );
    }

    /**
     * Generate speech from Khmer text using Azure TTS
     */
    public function generateSpeech(string $text, string $outputPath): string
    {
        try {
            $ssml = $this->createSSML($text);

            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->apiKey,
                'Content-Type' => 'application/ssml+xml',
                'X-Microsoft-OutputFormat' => 'audio-16khz-128kbitrate-mono-mp3',
            ])->withBody($ssml, 'application/ssml+xml')
              ->post($this->endpoint);

            if (!$response->successful()) {
                throw new \Exception('Azure TTS API error: ' . $response->body());
            }

            file_put_contents($outputPath, $response->body());

            Log::info('TTS generation completed', [
                'text_length' => strlen($text),
                'output_file' => $outputPath
            ]);

            return $outputPath;
        } catch (\Exception $e) {
            Log::error('TTS generation failed', [
                'error' => $e->getMessage(),
                'text' => substr($text, 0, 100)
            ]);
            throw $e;
        }
    }

    /**
     * Create SSML for Khmer language
     */
    private function createSSML(string $text): string
    {
        return <<<XML
<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="km-KH">
    <voice name="km-KH-SreymomNeural">
        <prosody rate="medium" pitch="medium">
            {$text}
        </prosody>
    </voice>
</speak>
XML;
    }

    /**
     * Generate speech using Edge TTS as fallback
     */
    public function generateSpeechEdgeTTS(string $text, string $outputPath): string
    {
        try {
            $edgeTtsUrl = 'https://speech.platform.bing.com/consumer/speech/synthesize/readaloud/edges/v1';

            $ssml = $this->createEdgeSSML($text);

            $response = Http::withHeaders([
                'Content-Type' => 'application/ssml+xml',
                'X-Microsoft-OutputFormat' => 'audio-16khz-128kbitrate-mono-mp3',
            ])->withBody($ssml, 'application/ssml+xml')
              ->post($edgeTtsUrl);

            if (!$response->successful()) {
                throw new \Exception('Edge TTS API error: ' . $response->body());
            }

            file_put_contents($outputPath, $response->body());

            Log::info('Edge TTS generation completed', [
                'text_length' => strlen($text),
                'output_file' => $outputPath
            ]);

            return $outputPath;
        } catch (\Exception $e) {
            Log::error('Edge TTS generation failed', [
                'error' => $e->getMessage(),
                'text' => substr($text, 0, 100)
            ]);
            throw $e;
        }
    }

    /**
     * Create SSML for Edge TTS
     */
    private function createEdgeSSML(string $text): string
    {
        return <<<XML
<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xmlns:mstts="https://www.w3.org/2001/mstts" xml:lang="km-KH">
    <voice name="Microsoft Speech Server - Khmer (Cambodia)">
        <mstts:express-as style="general">
            {$text}
        </mstts:express-as>
    </voice>
</speak>
XML;
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
                    'file' => $outputFile,
                    'start_time' => $segment['start_time'] ?? 0,
                    'end_time' => $segment['end_time'] ?? 0,
                    'text' => $segment['translated'],
                ];
            } catch (\Exception $e) {
                Log::error('Failed to generate TTS for segment', [
                    'index' => $index,
                    'segment' => $segment,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $audioFiles;
    }
}