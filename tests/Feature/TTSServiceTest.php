<?php

namespace Tests\Feature;

use App\Services\TTSService;
use Tests\TestCase;

class TTSServiceTest extends TestCase
{
    public function test_it_reuses_generated_segment_audio_on_retry(): void
    {
        $directory = storage_path('app/testing_tts_cache');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $outputPath = $directory . '/segment.mp3';
        file_put_contents($outputPath, 'cached-audio');

        try {
            $result = (new TTSService())->generateSpeechWithTimingBatch([[
                'text' => 'សួស្តី',
                'output_path' => $outputPath,
                'duration' => 3,
            ]]);

            $this->assertSame([$outputPath], $result);
            $this->assertSame('cached-audio', file_get_contents($outputPath));
        } finally {
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
}
