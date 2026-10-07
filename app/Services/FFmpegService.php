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
     * Merge Khmer audio and the separated original background audio with the video.
     */
    public function mergeAudioWithVideo(
        string $videoFilePath,
        string $audioFilePath,
        int $backgroundVolume = 50
    ): string
    {
        $backgroundGain = self::backgroundGainForVolume($backgroundVolume);

        $backgroundAudioPath = null;

        try {
            $outputFileName = 'translated_' . time() . '_' . pathinfo($videoFilePath, PATHINFO_FILENAME) . '.mp4';
            $outputFilePath = $this->processedPath . '/' . $outputFileName;
            $backgroundAudioPath = (new BackgroundAudioSeparationService())
                ->extractBackgroundAudio($videoFilePath);

            $cmd = sprintf(
                '%s -i %s -i %s -i %s -filter_complex %s -map 0:v:0 -map "[aout]" -c:v copy -c:a aac -b:a 192k -shortest %s -y',
                env('FFMPEG_PATH', 'ffmpeg'),
                escapeshellarg($videoFilePath),
                escapeshellarg($backgroundAudioPath),
                escapeshellarg($audioFilePath),
                escapeshellarg(sprintf(
                    '[1:a]volume=%.2f[bg];[bg][2:a]amix=inputs=2:duration=longest:dropout_transition=0:normalize=0,alimiter=limit=0.95[aout]',
                    $backgroundGain
                )),
                escapeshellarg($outputFilePath)
            );

            exec($cmd . ' 2>&1', $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \RuntimeException(
                    'FFmpeg merge failed with exit code: ' . $returnCode . ': '
                    . implode("\n", array_slice($output, -10))
                );
            }

            if (!file_exists($outputFilePath) || filesize($outputFilePath) === 0) {
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
        } finally {
            if ($backgroundAudioPath !== null && file_exists($backgroundAudioPath)) {
                unlink($backgroundAudioPath);
            }
        }
    }

    public static function backgroundGainForVolume(int $backgroundVolume): float
    {
        if ($backgroundVolume < 0 || $backgroundVolume > 100) {
            throw new \InvalidArgumentException('Background volume must be between 0 and 100.');
        }

        return $backgroundVolume / 50;
    }

    public function generateThumbnail(string $videoFilePath, string $outputPath): string
    {
        $directory = dirname($outputPath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create video thumbnail directory: {$directory}");
        }

        $this->runThumbnailCommand($videoFilePath, $outputPath, '00:00:01.000');
        if (!is_file($outputPath) || filesize($outputPath) === 0) {
            $this->runThumbnailCommand($videoFilePath, $outputPath, '00:00:00.000');
        }

        if (!is_file($outputPath) || filesize($outputPath) === 0) {
            throw new \RuntimeException('FFmpeg did not create a usable video thumbnail.');
        }

        return $outputPath;
    }

    private function runThumbnailCommand(string $videoFilePath, string $outputPath, string $seek): void
    {
        $command = sprintf(
            '%s -ss %s -i %s -frames:v 1 -vf %s -q:v 3 %s -y',
            env('FFMPEG_PATH', 'ffmpeg'),
            $seek,
            escapeshellarg($videoFilePath),
            escapeshellarg('scale=640:-2'),
            escapeshellarg($outputPath)
        );
        $output = [];
        $returnCode = null;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            Log::warning('Video thumbnail extraction attempt failed', [
                'video_file' => $videoFilePath,
                'seek' => $seek,
                'output' => implode("\n", array_slice($output, -5)),
            ]);
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
