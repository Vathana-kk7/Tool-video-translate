<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TTSService
{
    /**
     * Generate speech
     */
    public function generateSpeech(string $text, string $outputPath): string
    {
        try {
            echo "DEBUG: Inside generateSpeech method\n";
            
            $apiKey = config('services.tts.key', env('TTS_API_KEY'));
            $url    = config('services.tts.url', env('TTS_API_URL'));

            echo "DEBUG: API Key = '".$apiKey."'\n";
            echo "DEBUG: URL = '".$url."'\n";
            echo "DEBUG: API Key empty? ".($apiKey ? 'No' : 'Yes')."\n";
            echo "DEBUG: URL empty? ".($url ? 'No' : 'Yes')."\n";

            if (!$apiKey || !$url) {
                echo "DEBUG: Throwing exception - missing config\n";
                throw new \Exception('Missing TTS config (API_KEY or URL)');
            }

            echo "DEBUG: Config check passed\n";
            return "success";

        } catch (\Exception $e) {
            Log::error('TTS Error', [
                'error' => $e->getMessage()
            ]);
            
            echo "DEBUG: Caught exception: ".$e->getMessage()."\n";
            throw $e;
        }
    }
}

// Test it
$tts = new TTSService();
try {
    $result = $tts->generateSpeech("test", "output.mp3");
    echo "Result: ".$result;
} catch (\Exception $e) {
    echo "Final Error: ".$e->getMessage();
}
?>