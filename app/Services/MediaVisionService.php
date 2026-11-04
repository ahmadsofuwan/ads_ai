<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaVisionService
{
    /**
     * Synchronize existing files in public/ads_videos and public/ads_media into media_assets table.
     */
    public function syncStorageDirectory(): void
    {
        $disk = Storage::disk('public');
        $disk->makeDirectory('ads_videos');
        $disk->makeDirectory('ads_media');
        $disk->makeDirectory('ads_frames');

        $files = array_merge(
            $disk->files('ads_videos'),
            $disk->files('ads_media')
        );

        foreach ($files as $relPath) {
            if (! preg_match('/\.(mp4|mov|m4v|webm|avi|jpg|jpeg|png|webp)$/i', $relPath)) {
                continue;
            }

            $exists = MediaAsset::where('file_path', $relPath)->exists();
            if ($exists) {
                continue;
            }

            $absPath = $disk->path($relPath);
            $size = file_exists($absPath) ? (int) filesize($absPath) : 0;
            $mime = file_exists($absPath) ? (@mime_content_type($absPath) ?: null) : null;
            $type = MediaAsset::detectMediaType($relPath, $mime);
            $baseName = basename($relPath);

            $asset = MediaAsset::create([
                'title' => Str::headline(pathinfo($baseName, PATHINFO_FILENAME)),
                'file_name' => $baseName,
                'file_path' => $relPath,
                'media_type' => $type,
                'mime_type' => $mime,
                'file_size' => $size,
            ]);

            $this->extractMetadataAndFrames($asset);
        }
    }

    /**
     * Store an uploaded video or image into the Media Storage Library and extract metadata/keyframes.
     */
    public function storeUploadedMedia(
        UploadedFile $file,
        ?string $customTitle = null,
        ?string $notes = null
    ): MediaAsset {
        $disk = Storage::disk('public');
        $disk->makeDirectory('ads_videos');
        $disk->makeDirectory('ads_media');
        $disk->makeDirectory('ads_frames');

        $originalName = $file->getClientOriginalName() ?: 'media_upload';
        $mime = $file->getMimeType();
        $mediaType = MediaAsset::detectMediaType($originalName, $mime);

        $folder = $mediaType === MediaAsset::TYPE_VIDEO ? 'ads_videos' : 'ads_media';
        $storedPath = $file->store($folder, 'public');

        $absPath = $disk->path($storedPath);
        $fileSize = file_exists($absPath) ? (int) filesize($absPath) : (int) $file->getSize();

        $title = trim((string) $customTitle) !== ''
            ? trim((string) $customTitle)
            : Str::headline(pathinfo($originalName, PATHINFO_FILENAME));

        $asset = MediaAsset::create([
            'title' => $title ?: 'Media Iklan',
            'file_name' => basename($storedPath),
            'file_path' => $storedPath,
            'media_type' => $mediaType,
            'mime_type' => $mime,
            'file_size' => $fileSize,
            'notes' => trim((string) $notes) ?: null,
        ]);

        return $this->extractMetadataAndFrames($asset);
    }

    /**
     * Extract image/video dimensions, duration, and up to 3 representative keyframe JPEGs for Video Vision.
     */
    public function extractMetadataAndFrames(MediaAsset $asset): MediaAsset
    {
        $absPath = $asset->getAbsolutePath();
        if (! file_exists($absPath) || ! is_readable($absPath)) {
            return $asset;
        }

        if ($asset->isImage()) {
            $info = @getimagesize($absPath);
            if (is_array($info)) {
                $asset->update([
                    'width' => (int) ($info[0] ?? 0) ?: null,
                    'height' => (int) ($info[1] ?? 0) ?: null,
                    'mime_type' => $info['mime'] ?? $asset->mime_type,
                ]);
            }

            return $asset->fresh();
        }

        // Video metadata via ffprobe & keyframe extraction via ffmpeg
        $duration = null;
        $width = null;
        $height = null;

        if ($this->hasBinary('ffprobe')) {
            $cmd = sprintf(
                'ffprobe -v error -select_streams v:0 -show_entries stream=width,height,duration -show_entries format=duration -of json %s 2>/dev/null',
                escapeshellarg($absPath)
            );
            $rawJson = @shell_exec($cmd);
            if (is_string($rawJson) && $rawJson !== '') {
                $parsed = json_decode($rawJson, true);
                if (is_array($parsed)) {
                    $stream = $parsed['streams'][0] ?? [];
                    $format = $parsed['format'] ?? [];
                    $width = isset($stream['width']) ? (int) $stream['width'] : null;
                    $height = isset($stream['height']) ? (int) $stream['height'] : null;
                    $durVal = $stream['duration'] ?? ($format['duration'] ?? null);
                    if (is_numeric($durVal) && (float) $durVal > 0) {
                        $duration = round((float) $durVal, 2);
                    }
                }
            }
        }

        $extractedFrames = $asset->extracted_frames ?? [];

        if (empty($extractedFrames) && $this->hasBinary('ffmpeg')) {
            $disk = Storage::disk('public');
            $disk->makeDirectory('ads_frames');

            $totalDur = ($duration && $duration > 1.0) ? $duration : 6.0;
            $timestamps = [
                round(max(0.2, $totalDur * 0.15), 2),
                round(max(0.5, $totalDur * 0.50), 2),
                round(max(1.0, $totalDur * 0.85), 2),
            ];

            $frames = [];
            foreach ($timestamps as $idx => $sec) {
                $frameRelPath = "ads_frames/asset_{$asset->id}_f" . ($idx + 1) . '.jpg';
                $frameAbsPath = $disk->path($frameRelPath);

                $ffmpegCmd = sprintf(
                    'ffmpeg -y -ss %s -i %s -frames:v 1 -vf "scale=\'min(512,iw)\':-2" -q:v 4 %s 2>/dev/null',
                    escapeshellarg((string) $sec),
                    escapeshellarg($absPath),
                    escapeshellarg($frameAbsPath)
                );
                @shell_exec($ffmpegCmd);

                if (file_exists($frameAbsPath) && filesize($frameAbsPath) > 0) {
                    $frames[] = $frameRelPath;
                }
            }

            if (! empty($frames)) {
                $extractedFrames = $frames;
            }
        }

        $asset->update([
            'duration_seconds' => $duration ?? $asset->duration_seconds,
            'width' => $width ?? $asset->width,
            'height' => $height ?? $asset->height,
            'extracted_frames' => ! empty($extractedFrames) ? $extractedFrames : null,
        ]);

        return $asset->fresh();
    }

    /**
     * Resolve or register a MediaAsset from a relative file path on the public disk.
     */
    public function resolveOrCreateAssetFromPath(?string $relativePath): ?MediaAsset
    {
        if (empty($relativePath)) {
            return null;
        }

        $clean = preg_replace('#^public/#', '', trim($relativePath));
        $asset = MediaAsset::where('file_path', $clean)->first();

        if ($asset) {
            return $asset;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($clean)) {
            return null;
        }

        $absPath = $disk->path($clean);
        $size = file_exists($absPath) ? (int) filesize($absPath) : 0;
        $mime = file_exists($absPath) ? (@mime_content_type($absPath) ?: null) : null;
        $type = MediaAsset::detectMediaType($clean, $mime);

        $asset = MediaAsset::create([
            'title' => Str::headline(pathinfo($clean, PATHINFO_FILENAME)),
            'file_name' => basename($clean),
            'file_path' => $clean,
            'media_type' => $type,
            'mime_type' => $mime,
            'file_size' => $size,
        ]);

        return $this->extractMetadataAndFrames($asset);
    }

    /**
     * Build an array of Base64 image data URIs (data:image/jpeg;base64,...) for Multimodal Vision AI.
     * - For an IMAGE asset: encodes the image directly.
     * - For a VIDEO asset: encodes the extracted keyframe JPEGs (up to 3 frames).
     *
     * @return array<int, string>
     */
    public function buildVisionDataUris(?string $relativePath = null, ?MediaAsset $asset = null): array
    {
        $asset = $asset ?? $this->resolveOrCreateAssetFromPath($relativePath);

        if (! $asset) {
            return [];
        }

        if ($asset->isImage()) {
            $absPath = $asset->getAbsolutePath();
            if (file_exists($absPath) && is_readable($absPath)) {
                $bytes = @file_get_contents($absPath);
                if ($bytes !== false && $bytes !== '') {
                    $mime = $asset->mime_type ?: 'image/jpeg';
                    if (! str_starts_with($mime, 'image/')) {
                        $mime = 'image/jpeg';
                    }

                    return ['data:' . $mime . ';base64,' . base64_encode($bytes)];
                }
            }

            return [];
        }

        // For VIDEO: ensure frames are extracted
        if (empty($asset->extracted_frames)) {
            $asset = $this->extractMetadataAndFrames($asset);
        }

        $uris = [];
        $disk = Storage::disk('public');

        foreach (($asset->extracted_frames ?? []) as $frameRelPath) {
            $frameAbs = $disk->path($frameRelPath);
            if (file_exists($frameAbs) && is_readable($frameAbs)) {
                $bytes = @file_get_contents($frameAbs);
                if ($bytes !== false && $bytes !== '') {
                    $uris[] = 'data:image/jpeg;base64,' . base64_encode($bytes);
                }
            }
        }

        return array_slice($uris, 0, 3);
    }

    protected function hasBinary(string $binary): bool
    {
        $out = @shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null');

        return is_string($out) && trim($out) !== '';
    }
}
