<?php

namespace Tests\Feature;

use App\Jobs\UpdateBackgroundAudioJob;
use App\Models\Video;
use App\Services\FFmpegService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackgroundAudioVolumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_a_video_rebuild_for_the_selected_background_volume(): void
    {
        Queue::fake();
        $video = Video::create([
            'original_video' => 'videos/source.mp4',
            'khmer_audio' => 'audio/khmer.mp3',
            'final_video' => 'processed/translated.mp4',
            'status' => 'completed',
            'progress' => 100,
        ]);

        $response = $this->postJson("/api/videos/{$video->id}/background-audio", [
            'background_audio_volume' => 70,
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.status', 'merging')
            ->assertJsonPath('data.background_audio_volume', 50);
        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'status' => 'merging',
            'final_video' => 'processed/translated.mp4',
            'background_audio_volume' => 50,
        ]);
        Queue::assertPushedOn('high', UpdateBackgroundAudioJob::class, fn ($job) =>
            $job->videoId === $video->id && $job->backgroundVolume === 70
        );
    }

    public function test_it_rejects_background_volume_outside_the_supported_range(): void
    {
        Queue::fake();

        $this->postJson('/api/videos/111/background-audio', [
            'background_audio_volume' => 101,
        ])->assertUnprocessable();

        Queue::assertNothingPushed();
    }

    public function test_slider_volume_maps_fifty_percent_to_original_gain_and_one_hundred_to_double_gain(): void
    {
        $this->assertSame(0.0, FFmpegService::backgroundGainForVolume(0));
        $this->assertSame(1.0, FFmpegService::backgroundGainForVolume(50));
        $this->assertSame(2.0, FFmpegService::backgroundGainForVolume(100));
    }
}
