<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Video $video) {
            if ($video->background_audio_volume === null) {
                $video->background_audio_volume = 50;
            }
        });
    }

    protected $fillable = [
        'video_name',
        'original_video',
        'extracted_audio',
        'transcribed_text',
        'translated_text',
        'khmer_audio',
        'final_video',
        'subtitle_file',
        'status',
        'error_message',
        'progress',
        'background_audio_volume',
        'user_id',
        'segments',
        'audio_segments',
    ];

    protected $casts = [
        'segments' => 'array',
        'audio_segments' => 'array',
        'progress' => 'integer',
        'background_audio_volume' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'original_video_url',
        'final_video_url',
        'khmer_audio_url',
        'subtitle_url',
        'thumbnail_url',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function getOriginalVideoUrlAttribute(): string
    {
        return $this->original_video
            ? asset('storage/' . $this->original_video)
            : '';
    }

    public function getFinalVideoUrlAttribute(): string
    {
        return $this->final_video
            ? asset('storage/' . $this->final_video)
            : '';
    }

    public function getKhmerAudioUrlAttribute(): string
    {
        return $this->khmer_audio
            ? asset('storage/' . $this->khmer_audio)
            : '';
    }

    public function getSubtitleUrlAttribute(): string
    {
        return $this->subtitle_file
            ? asset('storage/' . $this->subtitle_file)
            : '';
    }

    public function getThumbnailUrlAttribute(): string
    {
        $thumbnailPath = storage_path('app/public/thumbnails/' . $this->id . '.jpg');

        return is_file($thumbnailPath)
            ? asset('storage/thumbnails/' . $this->id . '.jpg')
            : '';
    }

    public function canBeDownloaded(): bool
    {
        return $this->status === 'completed' && $this->final_video !== null;
    }
}