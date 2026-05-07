<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;

class FFmpegService
{
    protected string $uploadPath;
    protected string $audioPath;
    protected string $processedPath;

    public function __construct()
    {
        $this->uploadPath    = storage_path('app/public/videos');
        $this->audioPath     = storage_path('app/audio');
        $this->processedPath = storage_path('app/public/processed');

        foreach ([$this->uploadPath, $this->audioPath, $this->processedPath] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Extract and compress audio from video file
     * ✅ Compresses to mono 16kHz 32kbps to stay under Groq 25MB limit
     */
    public function extractAudio(string $videoFilePath): string
    {
        try {
            $audioFileName = pathinfo($videoFilePath, PATHINFO_FILENAME) . '.mp3';
            $audioFilePath = $this->audioPath . '/' . $audioFileName;

            // ✅ Use ffmpeg directly with compression settings
            $cmd = sprintf(
                '%s -i %s -vn -ar 16000 -ac 1 -b:a 32k %s -y',
                env('FFMPEG_PATH', 'ffmpeg'),
                escapeshellarg($videoFilePath),
                escapeshellarg($audioFilePath)
            );

            exec($cmd, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('Audio extraction failed with exit code: ' . $returnCode);
            }

            if (!file_exists($audioFilePath)) {
                throw new \Exception('Audio file was not created');
            }

            // ✅ Log file size for debugging
            $fileSizeMB = filesize($audioFilePath) / 1024 / 1024;
            Log::info('Audio extracted successfully', [
                'video_file'   => $videoFilePath,
                'audio_file'   => $audioFilePath,
                'file_size_mb' => round($fileSizeMB, 2),
            ]);

            return $audioFilePath;

        } catch (\Exception $e) {
            Log::error('Audio extraction failed', [
                'error'      => $e->getMessage(),
                'video_file' => $videoFilePath,
            ]);
            throw $e;
        }
    }

    /**
     * Merge Khmer audio with original video
     */
    public function mergeAudioWithVideo(string $videoFilePath, string $audioFilePath): string
    {
        try {
            $outputFileName = 'translated_' . time() . '_' . pathinfo($videoFilePath, PATHINFO_FILENAME) . '.mp4';
            $outputFilePath = $this->processedPath . '/' . $outputFileName;

            $cmd = sprintf(
                '%s -i %s -i %s -c:v copy -c:a aac -strict experimental -map 0:v:0 -map 1:a:0 -shortest %s -y',
                env('FFMPEG_PATH', 'ffmpeg'),
                escapeshellarg($videoFilePath),
                escapeshellarg($audioFilePath),
                escapeshellarg($outputFilePath)
            );

            exec($cmd, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('FFmpeg merge failed with exit code: ' . $returnCode);
            }

            if (!file_exists($outputFilePath)) {
                throw new \Exception('Merged video file was not created');
            }

            Log::info('Video merged successfully', [
                'video_file'  => $videoFilePath,
                'audio_file'  => $audioFilePath,
                'output_file' => $outputFilePath,
            ]);

            return $outputFilePath;

        } catch (\Exception $e) {
            Log::error('Video merge failed', [
                'error'      => $e->getMessage(),
                'video_file' => $videoFilePath,
                'audio_file' => $audioFilePath,
            ]);
            throw $e;
        }
    }

    /**
     * Get video duration
     */
    public function getVideoDuration(string $videoFilePath): float
    {
        try {
            $ffprobe = \FFMpeg\FFProbe::create([
                'ffprobe.binaries' => env('FFPROBE_PATH', 'ffprobe'),
                'timeout'          => 30,
            ]);

            $duration = $ffprobe
                ->streams($videoFilePath)
                ->videos()
                ->first()
                ->get('duration');

            return (float) $duration;

        } catch (\Exception $e) {
            Log::error('Failed to get video duration', [
                'error'      => $e->getMessage(),
                'video_file' => $videoFilePath,
            ]);
            return 0.0;
        }
    }

    /**
     * Validate video file
     */
    public function validateVideo(string $filePath): bool
    {
        try {
            $ffprobe = \FFMpeg\FFProbe::create([
                'ffprobe.binaries' => env('FFPROBE_PATH', 'ffprobe'),
                'timeout'          => 30,
            ]);

            $format = $ffprobe
                ->streams($filePath)
                ->videos()
                ->first();

            return $format !== null;

        } catch (\Exception $e) {
            return false;
        }
    }
}
