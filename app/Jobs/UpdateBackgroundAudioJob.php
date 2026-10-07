<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\FFmpegService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateBackgroundAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(
        public int $videoId,
        public int $backgroundVolume
    ) {}

    public function handle(): void
    {
        $video = Video::findOrFail($this->videoId);
        $originalVideoPath = storage_path('app/public/' . $video->original_video);
        $khmerAudioPath = storage_path('app/public/' . $video->khmer_audio);

        if (!is_file($originalVideoPath) || !is_file($khmerAudioPath)) {
            throw new \RuntimeException('Original video or generated Khmer audio file is missing.');
        }

        $finalVideoPath = (new FFmpegService())->mergeAudioWithVideo(
            $originalVideoPath,
            $khmerAudioPath,
            $this->backgroundVolume
        );
        $relativeVideoPath = str_replace(
            '\\',
            '/',
            str_replace(storage_path('app/public/'), '', $finalVideoPath)
        );

        $video->update([
            'final_video' => $relativeVideoPath,
            'background_audio_volume' => $this->backgroundVolume,
            'status' => 'completed',
            'progress' => 100,
            'error_message' => null,
        ]);

        Log::info('Video background audio updated successfully', [
            'video_id' => $this->videoId,
            'background_volume' => $this->backgroundVolume,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Video::whereKey($this->videoId)->update([
            'status' => 'completed',
            'progress' => 100,
            'error_message' => 'Background audio update failed: ' . $exception->getMessage(),
        ]);

        Log::error('Video background audio update failed', [
            'video_id' => $this->videoId,
            'background_volume' => $this->backgroundVolume,
            'error' => $exception->getMessage(),
        ]);
    }
}
