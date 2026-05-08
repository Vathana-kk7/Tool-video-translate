<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\FFmpegService;
use App\Services\TranslateService;
use App\Services\TTSService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 3600;
    public $backoff = 60;

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

            $video->update([
                'khmer_audio' => 'public/audio/' . $video->id . '_khmer.mp3', // ✅ កែត្រង់នេះ
                'audio_segments' => $audioFiles,
                'status' => 'processing',
                'progress' => 85,
            ]);

            Log::info('Video processing completed successfully', [
                'video_id' => $this->videoId
            ]);

        } catch (\Exception $e) {
            $video->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Video processing failed', [
                'video_id' => $this->videoId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    protected function extractAudio(Video $video): void
    {
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
        $video->update(['status' => 'transcribing', 'progress' => 30]);

        $whisperService = new \App\Services\WhisperService();
        $audioPath = storage_path('app/' . $video->extracted_audio);

        if (!file_exists($audioPath)) {
            throw new \Exception('Extracted audio file not found');
        }

        $transcription = $whisperService->transcribeWithSegments($audioPath);

        $video->update([
            'transcribed_text' => $transcription['full_text'],
            'segments' => $transcription['segments'] ?? [],
            'status' => 'processing',
            'progress' => 40,
        ]);
    }

    protected function translateText(Video $video): void
    {
        $video->update(['status' => 'translating', 'progress' => 50]);

        $translateService = new TranslateService();
        $segments = $video->segments ?? [];

        if (empty($segments)) {
            $translated = $translateService->translateToKhmer($video->transcribed_text);
            $video->update(['translated_text' => $translated]);
        } else {
            $translatedSegments = $translateService->translateBatch(
                array_column($segments, 'text')
            );

            foreach ($segments as $index => $segment) {
                $segments[$index]['translated'] = $translatedSegments[$index]['translated'] ?? $segment['text'];
            }

            $fullTranslated = implode('', array_column($segments, 'translated'));

            $video->update([
                'translated_text' => $fullTranslated,
                'segments' => $segments,
            ]);
        }

        $video->update(['status' => 'processing', 'progress' => 70]);
    }

    protected function generateTTS(Video $video): void
    {
        $video->update(['status' => 'generating_tts', 'progress' => 75]);

        $ttsService = new TTSService();
        $segments = $video->segments ?? [];

        $segmentsDir = storage_path('app/audio/segments/' . $video->id);
        $publicAudioDir = storage_path('app/public/audio');

        foreach ([$segmentsDir, $publicAudioDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        if (empty($segments)) {
            $tempAudioFile = $segmentsDir . '/full_text.mp3';
            $ttsService->generateSpeech($video->translated_text, $tempAudioFile);
            $audioFiles = [['file' => $tempAudioFile, 'start_time' => 0, 'end_time' => 0]];
        } else {
            $ttsSegments = [];
            foreach ($segments as $index => $segment) {
                $outputFile = $segmentsDir . '/segment_' . str_pad($index, 4, '0', STR_PAD_LEFT) . '.mp3';
                $ttsService->generateSpeech($segment['translated'], $outputFile);
                $ttsSegments[] = [
                    'file' => $outputFile,
                    'start_time' => $segment['start'] ?? 0,
                    'end_time' => $segment['end'] ?? 0,
                ];
            }
            $audioFiles = $ttsSegments;
        }

        $tempConcatenatedPath = $this->concatenateAudioFiles($audioFiles, $video->id);
        $publicAudioPath = $publicAudioDir . '/' . $video->id . '_khmer.mp3';
        copy($tempConcatenatedPath, $publicAudioPath);

        $isOriginalFile = count($audioFiles) === 1 ||
            ($audioFiles[0]['file'] ?? null) === $tempConcatenatedPath;

        if (!$isOriginalFile && file_exists($tempConcatenatedPath)) {
            unlink($tempConcatenatedPath);
        }

        $video->update([
            'khmer_audio' => 'audio/' . $video->id . '_khmer.mp3',
            'audio_segments' => $audioFiles,
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

    protected function concatenateAudioFiles(array $audioFiles, int $videoId): string
    {
        if (count($audioFiles) === 1) {
            return $audioFiles[0]['file'];
        }

        $outputFile = storage_path('app/audio/concatenated_' . $videoId . '.mp3');
        $listFile = storage_path('app/audio/list_' . $videoId . '.txt');

        $listContent = '';
        foreach ($audioFiles as $file) {
            $listContent .= "file '" . $file['file'] . "'\n";
        }
        file_put_contents($listFile, $listContent);

        $cmd = sprintf(
            '%s -f concat -safe 0 -i %s -c copy %s -y',
            env('FFMPEG_PATH', 'ffmpeg'),
            escapeshellarg($listFile),
            escapeshellarg($outputFile)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Audio concatenation failed');
        }

        foreach ($audioFiles as $file) {
            if (file_exists($file['file'])) unlink($file['file']);
        }
        unlink($listFile);

        return $outputFile;
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
