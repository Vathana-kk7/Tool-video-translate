<?php

namespace App\Jobs;

use App\Exceptions\TranslationRateLimitException;
use App\Models\Video;
use App\Services\FFmpegService;
use App\Services\SyncAudioService;
use App\Services\TranslateService;
use App\Services\TTSService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1000;
    public $timeout = 3600;
    public $backoff = [60, 120];

    public function __construct(
        public int $videoId
    ) {}

    public function handle(): void
    {
        $video = Video::findOrFail($this->videoId);

        try {
            $this->extractAudio($video);
            $this->transcribeAudio($video);
            $this->translateText($video);
            $this->generateTTS($video);
            $this->mergeVideo($video);

            Log::info('Video processing completed successfully', [
                'video_id' => $this->videoId
            ]);

        } catch (\Exception $e) {
            $attempts = $this->attempts();
            $isTranslationRateLimit = $e instanceof TranslationRateLimitException;
            $isTemporaryTtsFailure = $video->status === 'generating_tts'
                && str_contains(strtolower($e->getMessage()), 'no audio');
            $willRetry = ($isTranslationRateLimit || $isTemporaryTtsFailure)
                && $attempts < $this->tries;
            $retryMessage = $isTranslationRateLimit
                ? 'Temporary translation failure; retrying automatically. '
                : 'Temporary Khmer voice service failure; retrying automatically. ';
            $video->update([
                'status' => $willRetry ? $video->status : 'failed',
                'error_message' => $willRetry
                    ? $retryMessage . $e->getMessage()
                    : $e->getMessage(),
            ]);

            Log::error('Video processing failed', [
                'video_id' => $this->videoId,
                'attempt' => $attempts,
                'will_retry' => $willRetry,
                'error' => $e->getMessage()
            ]);

            if ($willRetry) {
                $this->release($isTranslationRateLimit ? $e->retryAfterSeconds : 60);
                return;
            }

            throw $e;
        }
    }

    protected function extractAudio(Video $video): void
    {
        $audioPath = $video->extracted_audio
            ? storage_path('app/' . $video->extracted_audio)
            : null;

        if ($audioPath && is_file($audioPath)) {
            return;
        }

        $video->update(['status' => 'extracting_audio', 'progress' => 10]);

        $ffmpegService = new FFmpegService();
        $originalPath = storage_path('app/public/' . $video->original_video);

        if (!file_exists($originalPath)) {
            throw new \Exception('Original video file not found');
        }

        $audioPath = $ffmpegService->extractAudio($originalPath);
        $relativeAudioPath = str_replace(storage_path('app/'), '', $audioPath);

        $video->update([
            'extracted_audio' => $relativeAudioPath,
            'status' => 'processing',
            'progress' => 20,
        ]);
    }

    protected function transcribeAudio(Video $video): void
    {
        if (trim((string) $video->transcribed_text) !== '') {
            return;
        }

        $video->update(['status' => 'transcribing', 'progress' => 30]);

        $whisperService = new \App\Services\WhisperService();
        $audioPath = storage_path('app/' . $video->extracted_audio);

        if (!file_exists($audioPath)) {
            throw new \Exception('Extracted audio file not found');
        }

        $transcription = $whisperService->transcribeWithSegments($audioPath);
        if (trim($transcription['full_text'] ?? '') === '') {
            throw new \RuntimeException('No Chinese speech was detected in the video.');
        }

        $video->update([
            'transcribed_text' => $transcription['full_text'],
            'segments' => $transcription['segments'] ?? [],
            'status' => 'processing',
            'progress' => 40,
        ]);
    }

    protected function translateText(Video $video): void
    {
        $translateService = new TranslateService();
        $segments = $video->segments ?? [];
        $translatedCount = collect($segments)
            ->filter(fn ($segment) => $this->hasKhmerTranslation((string) ($segment['translated'] ?? '')))
            ->count();
        $video->update([
            'status' => 'translating',
            'progress' => 50 + (int) floor(
                ($translatedCount / max(count($segments), 1)) * 20
            ),
        ]);

        if (empty($segments)) {
            if (trim((string) $video->translated_text) !== '') {
                $video->update(['progress' => 70]);
                return;
            }

            $translated = $translateService->translateToKhmer($video->transcribed_text);
            if (trim($translated) === '') {
                throw new \RuntimeException('No speech text was available to translate.');
            }
            $video->update(['translated_text' => $translated]);
        } else {
            $pendingIndexes = [];
            foreach ($segments as $index => $segment) {
                if (!$this->hasKhmerTranslation((string) ($segment['translated'] ?? ''))) {
                    $pendingIndexes[] = $index;
                }
            }

            $batches = array_chunk($pendingIndexes, 8);
            foreach ($batches as $batchIndex => $indexes) {
                $translatedSegments = $translateService->translateBatch(
                    array_map(fn ($index) => $segments[$index]['text'] ?? '', $indexes)
                );

                foreach ($indexes as $position => $index) {
                    $segments[$index]['translated'] = $translatedSegments[$position]['translated'];
                }

                $translatedCount = collect($segments)
                    ->filter(fn ($segment) => $this->hasKhmerTranslation((string) ($segment['translated'] ?? '')))
                    ->count();
                $video->update([
                    'translated_text' => implode('', array_column($segments, 'translated')),
                    'segments' => $segments,
                    'error_message' => null,
                    'progress' => 50 + (int) floor(
                        ($translatedCount / max(count($segments), 1)) * 20
                    ),
                ]);
            }
        }

        $video->update(['status' => 'processing', 'progress' => 70]);
    }

    private function hasKhmerTranslation(string $text): bool
    {
        return preg_match('/[\x{1780}-\x{17FF}]/u', $text) === 1;
    }

    protected function generateTTS(Video $video): void
    {
        $video->update([
            'status' => 'generating_tts',
            'progress' => 75,
            'error_message' => null,
        ]);

        $ttsService = new TTSService();
        $segments = $video->segments ?? [];

        $publicAudioDir = storage_path('app/public/audio');

        if (!is_dir($publicAudioDir) && !mkdir($publicAudioDir, 0755, true) && !is_dir($publicAudioDir)) {
            throw new \RuntimeException("Unable to create Khmer audio directory: {$publicAudioDir}");
        }

        $originalVideoPath = storage_path('app/public/' . $video->original_video);
        $videoDuration = (new FFmpegService())->getVideoDuration($originalVideoPath);

        if ($videoDuration <= 0) {
            throw new \RuntimeException('Unable to determine the original video duration.');
        }

        $publicAudioPath = $publicAudioDir . '/' . $video->id . '_khmer.mp3';
        if (empty($segments)) {
            $segments = [[
                'start' => 0,
                'end' => $videoDuration,
                'translated' => $video->translated_text,
            ]];
        }

        (new SyncAudioService($ttsService))->buildSyncedAudio(
            $segments,
            $videoDuration,
            $publicAudioPath
        );

        $video->update([
            'khmer_audio' => 'audio/' . $video->id . '_khmer.mp3',
            'audio_segments' => array_map(function ($segment) {
                return [
                    'start_time' => $segment['start'] ?? 0,
                    'end_time' => $segment['end'] ?? 0,
                    'text' => $segment['translated'] ?? '',
                ];
            }, $segments),
            'status' => 'processing',
            'progress' => 85,
        ]);
    }

    protected function mergeVideo(Video $video): void
    {
        $video->update(['status' => 'merging', 'progress' => 90]);

        $ffmpegService = new FFmpegService();
        $originalVideoPath = storage_path('app/public/' . $video->original_video);
        $khmerAudioPath = storage_path('app/public/' . $video->khmer_audio);

        if (!file_exists($originalVideoPath)) {
            throw new \Exception('Original video file not found');
        }

        if (!file_exists($khmerAudioPath)) {
            throw new \Exception('Khmer audio file not found');
        }

        $finalVideoPath = $ffmpegService->mergeAudioWithVideo($originalVideoPath, $khmerAudioPath);

        $publicPath = storage_path('app/public/');
        $relativeVideoPath = str_replace('\\', '/', str_replace($publicPath, '', $finalVideoPath));

        $video->update([
            'final_video' => $relativeVideoPath,
            'status' => 'completed',
            'progress' => 100,
            'error_message' => null,
        ]);

        $this->generateSubtitles($video);
    }

    protected function generateSubtitles(Video $video): void
    {
        try {
            $segments = $video->segments ?? [];
            if (empty($segments)) return;

            $srtContent = '';
            foreach ($segments as $index => $segment) {
                $start = $this->secondsToSRTTime($segment['start'] ?? 0);
                $end = $this->secondsToSRTTime($segment['end'] ?? 0);
                $srtContent .= ($index + 1) . "\n";
                $srtContent .= "{$start} --> {$end}\n";
                $srtContent .= ($segment['translated'] ?? $segment['text']) . "\n\n";
            }

            $subtitlesDir = storage_path('app/public/subtitles');
            if (!is_dir($subtitlesDir)) mkdir($subtitlesDir, 0755, true);

            file_put_contents($subtitlesDir . '/' . $video->id . '.srt', $srtContent);

            $video->update(['subtitle_file' => 'subtitles/' . $video->id . '.srt']);

            Log::info('Subtitles generated', ['video_id' => $video->id]);
        } catch (\Exception $e) {
            Log::error('Failed to generate subtitles', [
                'video_id' => $video->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function secondsToSRTTime(float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);
        $milliseconds = floor(($seconds - floor($seconds)) * 1000);

        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secs, $milliseconds);
    }
}
