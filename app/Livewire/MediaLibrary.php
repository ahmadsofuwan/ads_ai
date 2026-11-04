<?php

namespace App\Livewire;

use App\Models\AiProvider;
use App\Models\MediaAsset;
use App\Services\MediaEditorService;
use App\Services\MediaVisionService;
use App\Services\OpenRouterService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Media Storage, AI Vision & AI Video/Image Editor — MetaAds AI Publisher')]
class MediaLibrary extends Component
{
    use WithFileUploads;

    public $mediaFile = null;
    public string $uploadTitle = '';
    public string $uploadNotes = '';
    public bool $autoAnalyzeVision = true;
    public ?int $selectedAiProviderId = null;

    public string $typeFilter = '';
    public string $search = '';

    // AI Media Studio Editor state
    public ?int $editingAssetId = null;
    public string $aiEditPrompt = '';
    public string $editOutputMode = 'KEEP_TYPE'; // KEEP_TYPE or IMAGE_TO_VIDEO
    public string $editAspectRatio = '9:16';
    public string $editFitMode = 'CROP';
    public string $editColorPreset = 'VIBRANT';
    public string $editOverlayText = '';
    public string $editOverlaySubtext = '';
    public string $editOverlayPosition = 'TOP';
    public string $editOverlayTheme = 'RED';
    public float $editTrimStart = 0.0;
    public float $editTrimDuration = 0.0;
    public float $editSpeed = 1.0;
    public int $editVideoDuration = 8;
    public string $editNewTitle = '';

    public ?string $flashMessage = null;
    public string $flashType = 'success';

    public function mount(MediaVisionService $visionService): void
    {
        $visionService->syncStorageDirectory();

        $defaultAi = AiProvider::where('is_active', true)->orderByDesc('is_default')->orderBy('priority')->first();
        if ($defaultAi) {
            $this->selectedAiProviderId = $defaultAi->id;
        }
    }

    public function uploadToStorage(MediaVisionService $visionService, OpenRouterService $openRouterService): void
    {
        $this->validate([
            'mediaFile' => 'required|file|max:204800', // up to 200MB
            'uploadTitle' => 'nullable|string|max:180',
            'uploadNotes' => 'nullable|string|max:1000',
        ], [
            'mediaFile.required' => 'Pilih file video (MP4/MOV/WEBM) atau gambar (JPG/PNG/WEBP) terlebih dahulu.',
        ]);

        try {
            $asset = $visionService->storeUploadedMedia(
                $this->mediaFile,
                $this->uploadTitle,
                $this->uploadNotes
            );

            if ($this->autoAnalyzeVision) {
                $openRouterService->analyzeMediaVisual($asset, null, $this->selectedAiProviderId);
                $asset->refresh();
            }

            $this->mediaFile = null;
            $this->uploadTitle = '';
            $this->uploadNotes = '';

            $this->flashType = 'success';
            $this->flashMessage = "✅ Media \"{$asset->title}\" ({$asset->media_type}) berhasil disimpan ke Storage Library"
                . ($this->autoAnalyzeVision ? ' beserta ekstraksi frame & analisa AI Vision!' : '!');
        } catch (\Throwable $e) {
            $this->flashType = 'error';
            $this->flashMessage = '❌ Gagal mengunggah media ke storage: ' . $e->getMessage();
        }
    }

    public function analyzeAssetVision(int $assetId, OpenRouterService $openRouterService): void
    {
        $asset = MediaAsset::find($assetId);
        if (! $asset) {
            return;
        }

        try {
            $summary = $openRouterService->analyzeMediaVisual($asset, null, $this->selectedAiProviderId);
            $this->flashType = 'success';
            $this->flashMessage = "👁️ Analisa AI Vision untuk \"{$asset->title}\" selesai: " . $summary;
        } catch (\Throwable $e) {
            $this->flashType = 'error';
            $this->flashMessage = '❌ Gagal menganalisa visual media: ' . $e->getMessage();
        }
    }

    public function openEditor(int $assetId, OpenRouterService $openRouterService): void
    {
        $asset = MediaAsset::find($assetId);
        if (! $asset) {
            return;
        }

        $this->editingAssetId = $asset->id;
        $this->aiEditPrompt = '';

        $defaults = $openRouterService->extractHeuristicMediaEditOptions($asset, '');
        $this->editOutputMode = $asset->isImage() ? 'KEEP_TYPE' : 'KEEP_TYPE';
        $this->editAspectRatio = (string) ($defaults['aspect_ratio'] ?? '9:16');
        $this->editFitMode = (string) ($defaults['fit_mode'] ?? 'CROP');
        $this->editColorPreset = (string) ($defaults['color_preset'] ?? 'VIBRANT');
        $this->editOverlayText = (string) ($defaults['overlay_text'] ?? '');
        $this->editOverlaySubtext = (string) ($defaults['overlay_subtext'] ?? '');
        $this->editOverlayPosition = (string) ($defaults['overlay_position'] ?? 'TOP');
        $this->editOverlayTheme = (string) ($defaults['overlay_theme'] ?? 'RED');
        $this->editTrimStart = 0.0;
        $this->editTrimDuration = 0.0;
        $this->editSpeed = 1.0;
        $this->editVideoDuration = 8;
        $this->editNewTitle = $asset->title . ' (AI Edited)';
    }

    public function closeEditor(): void
    {
        $this->editingAssetId = null;
        $this->aiEditPrompt = '';
    }

    public function applyAiEditSuggestion(OpenRouterService $openRouterService): void
    {
        if (! $this->editingAssetId) {
            return;
        }

        $asset = MediaAsset::find($this->editingAssetId);
        if (! $asset) {
            return;
        }

        try {
            $parsed = $openRouterService->parseMediaEditPrompt(
                $asset,
                $this->aiEditPrompt,
                null,
                $this->selectedAiProviderId
            );

            $this->editOutputMode = (string) ($parsed['output_mode'] ?? 'KEEP_TYPE');
            $this->editAspectRatio = (string) ($parsed['aspect_ratio'] ?? '9:16');
            $this->editFitMode = (string) ($parsed['fit_mode'] ?? 'CROP');
            $this->editColorPreset = (string) ($parsed['color_preset'] ?? 'VIBRANT');
            $this->editOverlayText = (string) ($parsed['overlay_text'] ?? '');
            $this->editOverlaySubtext = (string) ($parsed['overlay_subtext'] ?? '');
            $this->editOverlayPosition = (string) ($parsed['overlay_position'] ?? 'TOP');
            $this->editOverlayTheme = (string) ($parsed['overlay_theme'] ?? 'RED');
            $this->editTrimStart = (float) ($parsed['trim_start'] ?? 0.0);
            $this->editTrimDuration = (float) ($parsed['trim_duration'] ?? 0.0);
            $this->editSpeed = (float) ($parsed['speed'] ?? 1.0);
            $this->editVideoDuration = (int) ($parsed['video_duration'] ?? 8);
            $this->editNewTitle = (string) ($parsed['new_title'] ?? ($asset->title . ' (AI Edited)'));

            $summary = (string) ($parsed['ai_edit_summary'] ?? 'Parameter editing berhasil diisi oleh AI.');
            $this->flashType = 'success';
            $this->flashMessage = "🤖 AI menyarankan konfigurasi edit untuk \"{$asset->title}\": {$summary}";
        } catch (\Throwable $e) {
            $this->flashType = 'error';
            $this->flashMessage = '❌ Gagal meminta saran edit AI: ' . $e->getMessage();
        }
    }

    public function executeAiMediaEdit(
        MediaEditorService $editorService,
        OpenRouterService $openRouterService,
        bool $parsePromptFirst = false
    ): void {
        if (! $this->editingAssetId) {
            return;
        }

        $source = MediaAsset::find($this->editingAssetId);
        if (! $source) {
            return;
        }

        try {
            if ($parsePromptFirst && trim($this->aiEditPrompt) !== '') {
                $this->applyAiEditSuggestion($openRouterService);
            }

            $editedAsset = $editorService->editMediaAsset($source, [
                'output_mode' => $this->editOutputMode,
                'aspect_ratio' => $this->editAspectRatio,
                'fit_mode' => $this->editFitMode,
                'color_preset' => $this->editColorPreset,
                'overlay_text' => $this->editOverlayText,
                'overlay_subtext' => $this->editOverlaySubtext,
                'overlay_position' => $this->editOverlayPosition,
                'overlay_theme' => $this->editOverlayTheme,
                'trim_start' => $this->editTrimStart,
                'trim_duration' => $this->editTrimDuration,
                'speed' => $this->editSpeed,
                'video_duration' => $this->editVideoDuration,
                'new_title' => $this->editNewTitle,
            ]);

            $this->editingAssetId = null;
            $this->aiEditPrompt = '';
            $this->flashType = 'success';
            $this->flashMessage = "🪄 Berhasil merender & menyimpan media hasil edit AI: \"{$editedAsset->title}\" ({$editedAsset->media_type} • {$editedAsset->width}x{$editedAsset->height}px)!";
        } catch (\Throwable $e) {
            $this->flashType = 'error';
            $this->flashMessage = '❌ Gagal mengedit media: ' . $e->getMessage();
        }
    }

    public function deleteAsset(int $assetId): void
    {
        $asset = MediaAsset::find($assetId);
        if (! $asset) {
            return;
        }

        $disk = Storage::disk('public');
        if ($asset->file_path && $disk->exists($asset->file_path)) {
            $disk->delete($asset->file_path);
        }

        foreach (($asset->extracted_frames ?? []) as $framePath) {
            if ($disk->exists($framePath)) {
                $disk->delete($framePath);
            }
        }

        $title = $asset->title;
        $asset->delete();

        if ($this->editingAssetId === $assetId) {
            $this->editingAssetId = null;
        }

        $this->flashType = 'success';
        $this->flashMessage = "🗑️ Media \"{$title}\" berhasil dihapus dari Storage.";
    }

    public function render()
    {
        $query = MediaAsset::query()->orderByDesc('created_at');

        if ($this->typeFilter !== '') {
            $query->where('media_type', strtoupper($this->typeFilter));
        }

        if (trim($this->search) !== '') {
            $q = '%' . trim($this->search) . '%';
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', $q)
                    ->orWhere('file_name', 'like', $q)
                    ->orWhere('notes', 'like', $q)
                    ->orWhere('ai_visual_analysis', 'like', $q);
            });
        }

        $editingAsset = $this->editingAssetId ? MediaAsset::find($this->editingAssetId) : null;

        return view('livewire.media-library', [
            'assets' => $query->get(),
            'editingAsset' => $editingAsset,
            'aiProviders' => AiProvider::where('is_active', true)->orderByDesc('is_default')->orderBy('priority')->get(),
        ]);
    }
}
