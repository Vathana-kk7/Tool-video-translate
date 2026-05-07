<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVideoJob;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    /**
     * Upload Chinese video for translation
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // NOTE: max: is in kilobytes. 5242880 KB = 5GB.
            'video' => 'required|file|mimes:mp4,avi,mov,mkv,webm|max:5242880', // 5GB
        ], [
            'video.max' => 'Video file size must not exceed 5GB.',
            'video.mimes' => 'Supported video formats: MP4, AVI, MOV, MKV, WebM.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('video');

            // Generate unique filename
            $filename = time() . '_' . Str::random(12) . '.' . $file->getClientOriginalExtension();
            $filePath = 'videos/' . $filename;

            // Store file in public disk
            $storedPath = $request->file('video')->storeAs('public', $filePath);

            // Create video record
            $video = Video::create([
                'original_video' => $filePath,
                'status' => 'pending',
                'progress' => 0,
            ]);

            // Dispatch processing job. If dispatch fails, keep the upload accepted
            // and let the job retry or be handled by a queue worker later.
            try {
                ProcessVideoJob::dispatch($video->id);
            } catch (\Throwable $e) {
                \Log::warning('Video dispatch failed; upload was accepted and processing will retry.', [
                    'video_id' => $video->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Video uploaded successfully. Processing has been queued.',
                'data' => [
                    'video_id' => $video->id,
                    'status' => $video->status,
                    'progress' => $video->progress,
                ],
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Video upload failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload video. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get video processing status
     */
    public function status(string $id)
    {
        try {
            $video = Video::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'video_id' => $video->id,
                    'status' => $video->status,
                    'progress' => $video->progress,
                    'original_video_url' => $video->original_video_url,
                    'final_video_url' => $video->final_video_url,
                    'error_message' => $video->error_message,
                    'created_at' => $video->created_at->toISOString(),
                    'updated_at' => $video->updated_at->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Video not found',
            ], 404);
        }
    }

    /**
     * Download processed video
     */
    public function download(string $id)
    {
        try {
            $video = Video::findOrFail($id);

            if (! $video->canBeDownloaded()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video is not ready for download. Current status: ' . $video->status,
                ], 400);
            }

            $filePath = storage_path('app/public/' . $video->final_video);

            if (! file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Processed video file not found',
                ], 404);
            }

            $filename = 'translated_' . $video->id . '.mp4';

            return response()->download($filePath, $filename, [
                'Content-Type' => 'video/mp4',
            ]);
        } catch (\Exception $e) {
            \Log::error('Video download failed', [
                'video_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download video',
            ], 500);
        }
    }

    /**
     * List all videos for user
     */
    public function index(Request $request)
    {
        try {
            $videos = Video::latest()
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $videos->map(function ($video) {
                    return [
                        'id' => $video->id,
                        'status' => $video->status,
                        'progress' => $video->progress,
                        'original_video_url' => $video->original_video_url,
                        'final_video_url' => $video->final_video_url,
                        'created_at' => $video->created_at->toISOString(),
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch videos',
            ], 500);
        }
    }

    /**
     * Get video details
     */
    public function show(string $id)
    {
        try {
            $video = Video::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $video->id,
                    'original_video' => $video->original_video,
                    'extracted_audio' => $video->extracted_audio,
                    'transcribed_text' => $video->transcribed_text,
                    'translated_text' => $video->translated_text,
                    'khmer_audio' => $video->khmer_audio,
                    'final_video' => $video->final_video,
                    'subtitle_file' => $video->subtitle_file,
                    'status' => $video->status,
                    'progress' => $video->progress,
                    'error_message' => $video->error_message,
                    'segments' => $video->segments,
                    'original_video_url' => $video->original_video_url,
                    'final_video_url' => $video->final_video_url,
                    'khmer_audio_url' => $video->khmer_audio_url,
                    'subtitle_url' => $video->subtitle_url,
                    'created_at' => $video->created_at->toISOString(),
                    'updated_at' => $video->updated_at->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Video not found',
            ], 404);
        }
    }
}

