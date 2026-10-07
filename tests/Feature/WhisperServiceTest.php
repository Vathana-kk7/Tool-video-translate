<?php

namespace Tests\Feature;

use App\Services\WhisperService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhisperServiceTest extends TestCase
{
    public function test_it_splits_audio_larger_than_the_provider_limit_and_offsets_segments(): void
    {
        $directory = storage_path('app/testing_whisper_chunks');
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            $this->fail('Unable to create the transcription test directory.');
        }

        $audioPath = $directory . DIRECTORY_SEPARATOR . 'long-audio.mp3';

        try {
            $command = sprintf(
                '%s -f lavfi -i anullsrc=r=16000:cl=mono -t 6600 -c:a libmp3lame -b:a 32k %s -y',
                env('FFMPEG_PATH', 'ffmpeg'),
                escapeshellarg($audioPath)
            );
            exec($command . ' 2>&1', $output, $returnCode);

            if ($returnCode !== 0 || !is_file($audioPath)) {
                $this->fail('Unable to create long transcription test audio: ' . implode("\n", $output));
            }
            $this->assertGreaterThan(24 * 1024 * 1024, filesize($audioPath));

            Http::fake([
                'api.groq.com/*' => Http::response([
                    'text' => '你好。',
                    'segments' => [[
                        'start' => 0,
                        'end' => 1,
                        'text' => '你好。',
                    ]],
                ]),
            ]);

            $result = (new WhisperService())->transcribeWithSegments($audioPath);

            $this->assertSame('你好。你好。你好。你好。你好。你好。', $result['full_text']);
            $this->assertCount(6, $result['segments']);
            $this->assertGreaterThan(1199, $result['segments'][1]['start']);
            $this->assertCount(6, Http::recorded());
        } finally {
            if (is_file($audioPath)) {
                unlink($audioPath);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
}
