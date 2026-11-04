<?php

namespace App\Livewire;

use App\Models\AiProvider;
use App\Models\MediaAsset;
use App\Services\MediaVisionService;
use App\Services\OpenRouterService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Media Storage Library & AI Vision — MetaAds AI Publisher')]
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

        return view('livewire.media-library', [
            'assets' => $query->get(),
            'aiProviders' => AiProvider::where('is_active', true)->orderByDesc('is_default')->orderBy('priority')->get(),
        ]);
    }
}
