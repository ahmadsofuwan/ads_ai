<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    public const TYPE_VIDEO = 'VIDEO';
    public const TYPE_IMAGE = 'IMAGE';

    protected $table = 'media_assets';

    protected $fillable = [
        'title',
        'file_name',
        'file_path',
        'media_type',
        'mime_type',
        'file_size',
        'duration_seconds',
        'width',
        'height',
        'extracted_frames',
        'ai_visual_analysis',
        'notes',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'duration_seconds' => 'float',
        'width' => 'integer',
        'height' => 'integer',
        'extracted_frames' => 'array',
    ];

    public function isVideo(): bool
    {
        return strtoupper((string) $this->media_type) === self::TYPE_VIDEO;
    }

    public function isImage(): bool
    {
        return strtoupper((string) $this->media_type) === self::TYPE_IMAGE;
    }

    public function getUrlAttribute(): string
    {
        $relative = preg_replace('#^public/#', '', (string) $this->file_path);

        return Storage::disk('public')->url($relative);
    }

    public function getAbsolutePath(): string
    {
        $relative = preg_replace('#^public/#', '', (string) $this->file_path);
        $diskPath = Storage::disk('public')->path($relative);

        if (file_exists($diskPath)) {
            return $diskPath;
        }

        $fallback = storage_path('app/public/' . ltrim($relative, '/'));
        if (file_exists($fallback)) {
            return $fallback;
        }

        return (string) $this->file_path;
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) {
            return '0 KB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        return number_format(max(1, $bytes / 1024), 0) . ' KB';
    }

    public function getFrameUrlsAttribute(): array
    {
        $frames = $this->extracted_frames ?? [];
        if (! is_array($frames)) {
            return [];
        }

        return array_values(array_map(
            fn ($framePath) => Storage::disk('public')->url($framePath),
            $frames
        ));
    }

    public static function detectMediaType(string $pathOrExtension, ?string $mimeType = null): string
    {
        if ($mimeType && str_starts_with(strtolower($mimeType), 'image/')) {
            return self::TYPE_IMAGE;
        }

        $ext = strtolower(pathinfo($pathOrExtension, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return self::TYPE_IMAGE;
        }

        return self::TYPE_VIDEO;
    }
}
