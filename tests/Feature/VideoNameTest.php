<?php

namespace Tests\Feature;

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_upload_requires_a_name(): void
    {
        $this->postJson('/api/videos/upload', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['video_name', 'video']);
    }

    public function test_video_name_is_included_in_list_and_detail_responses(): void
    {
        $video = Video::create([
            'video_name' => 'ភាគទី 7',
            'original_video' => 'videos/episode-7.mp4',
            'status' => 'completed',
            'progress' => 100,
        ]);

        $this->getJson('/api/videos')
            ->assertOk()
            ->assertJsonPath('data.0.video_name', 'ភាគទី 7');

        $this->getJson("/api/videos/{$video->id}")
            ->assertOk()
            ->assertJsonPath('data.video_name', 'ភាគទី 7');
    }
}
