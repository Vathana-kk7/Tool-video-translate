<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TTSServiceTest
{
    /**
     * Test generate speech
     */
    public function testGenerateSpeech(string $text, string $outputPath): string
    {
        try {
            $apiKey = config('services.tts.key', env('TTS_API_KEY'));
            $url    = config('services.tts.url', env('TTS_API_URL'));

            echo "API Key from config: '".$apiKey."'\n";
            echo "URL from config: '".$url."'\n";
            echo "API Key length: ".strlen($apiKey)."\n";
            echo "URL length: ".strlen($url)."\n";

            if (!$apiKey || !$url) {
                throw new \Exception('Missing TTS config (API_KEY or URL)');
            }

            return "Test passed";

        } catch (\Exception $e) {
            Log::error('TTS Error', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}

// Test it
$test = new TTSServiceTest();
try {
    $result = $test->testGenerateSpeech("hello", "test.mp3");
    echo $result;
} catch (\Exception $e) {
    echo "Error: ".$e->getMessage();
}
?>