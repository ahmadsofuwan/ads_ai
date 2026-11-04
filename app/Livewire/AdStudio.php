<?php

namespace App\Livewire;

use App\Jobs\PublishGoogleAdJob;
use App\Jobs\PublishMetaAdJob;
use App\Jobs\PublishTiktokAdJob;
use App\Models\AdAccount;
use App\Models\AdDraft;
use App\Models\AiProvider;
use App\Models\MediaAsset;
use App\Models\Setting;
use App\Services\MediaVisionService;
use App\Services\MetaMarketingService;
use App\Services\OpenRouterService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Studio Pembuat Iklan AI Omni-Channel — MetaAds AI Publisher')]
class AdStudio extends Component
{
    use WithFileUploads;

    public $videoFile = null;
    public string $existingVideoPath = '';
    public ?int $selectedMediaAssetId = null;
    public string $aiVisualAnalysis = '';
    public array $availableVideos = [];

    public string $rawUserPrompt = 'Promo pasang WiFi rumah cepat tanpa batas kuota, radius 10km, umur 24-45 tahun, budget 50rb/hari arahkan ke WA.';

    public array $targetPlatforms = ['META', 'TIKTOK', 'GOOGLE'];

    // Multi-Account & Multi-Provider Selection
    public ?int $selectedAiProviderId = null;
    public ?int $metaAccountId = null;
    public ?int $tiktokAccountId = null;
    public ?int $googleAccountId = null;
    public array $selectedMetaAccountIds = [];
    public array $selectedTiktokAccountIds = [];
    public array $selectedGoogleAccountIds = [];

    public string $title = '';
    public string $objective = AdDraft::OBJECTIVE_ENGAGEMENT;
    public string $destinationType = AdDraft::DESTINATION_WHATSAPP;
    public string $destinationUrl = '';
    public int $dailyBudget = 50000;
    public int $ageMin = 21;
    public int $ageMax = 50;
    public string $genderSelection = 'all';

    public array $customLocations = [];
    public array $excludedLocations = [];
    public array $interests = [];
    public string $interestSearchQuery = '';
    public array $interestSearchResults = [];

    // Meta Copywriting
    public string $aiPrimaryText = '';
    public string $aiHeadline = '';
    public string $aiDescription = '';
    public string $ctaType = AdDraft::CTA_WHATSAPP_MESSAGE;

    // TikTok Copywriting & Location
    public string $tiktokAdText = '';
    public string $tiktokLocationId = '1643084';

    // Google / YouTube Copywriting
    public string $googleHeadline = '';
    public string $googleLongHeadline = '';
    public string $googleDescription = '';
    public string $youtubeVideoId = '';

    public bool $aiGenerated = false;
    public ?string $statusMessage = null;
    public string $statusType = 'success';

    public function mount(): void
    {
        $setting = Setting::current();

        $defaultMetaAcc = AdAccount::resolveAccountForPlatform(AdAccount::PROVIDER_META);
        if ($defaultMetaAcc) {
            $this->metaAccountId = $defaultMetaAcc->id;
            $this->selectedMetaAccountIds = [(string) $defaultMetaAcc->id];
        }

        $defaultTiktokAcc = AdAccount::resolveAccountForPlatform(AdAccount::PROVIDER_TIKTOK);
        if ($defaultTiktokAcc) {
            $this->tiktokAccountId = $defaultTiktokAcc->id;
            $this->selectedTiktokAccountIds = [(string) $defaultTiktokAcc->id];
        }

        $defaultGoogleAcc = AdAccount::resolveAccountForPlatform(AdAccount::PROVIDER_GOOGLE);
        if ($defaultGoogleAcc) {
            $this->googleAccountId = $defaultGoogleAcc->id;
            $this->selectedGoogleAccountIds = [(string) $defaultGoogleAcc->id];
        }

        $defaultAiProv = AiProvider::where('is_active', true)->orderByDesc('is_default')->orderBy('priority')->first();
        if ($defaultAiProv) {
            $this->selectedAiProviderId = $defaultAiProv->id;
        }

        $defaultLat = round((float) ($setting->default_latitude ?: -1.610123), 6);
        $defaultLng = round((float) ($setting->default_longitude ?: 103.613120), 6);

        $this->tiktokLocationId = (string) ($setting->tiktok_default_location_id ?: '1643084');

        $this->customLocations = [
            [
                'uid' => 'inc_1',
                'latitude' => $defaultLat,
                'longitude' => $defaultLng,
                'radius' => 10,
                'distance_unit' => 'kilometer',
                'name' => 'Titik Target Utama #1',
            ],
        ];

        $this->excludedLocations = [];

        if (! empty($setting->meta_whatsapp_number)) {
            $wa = preg_replace('/[^0-9]/', '', (string) $setting->meta_whatsapp_number);
            if (str_starts_with($wa, '0')) {
                $wa = '62' . substr($wa, 1);
            }
            if ($wa !== '') {
                $this->destinationUrl = "https://wa.me/{$wa}";
            }
        }

        $this->loadAvailableVideos();

        $reqMediaId = request()->query('media_id');
        if ($reqMediaId) {
            $asset = MediaAsset::find((int) $reqMediaId);
            if ($asset) {
                $this->selectedMediaAssetId = $asset->id;
                $this->existingVideoPath = $asset->file_path;
                $this->aiVisualAnalysis = (string) ($asset->ai_visual_analysis ?? '');
            }
        }
    }

    public function loadAvailableVideos(): void
    {
        $visionService = app(MediaVisionService::class);
        $visionService->syncStorageDirectory();

        $assets = MediaAsset::query()->orderByDesc('created_at')->get();
        $list = [];
        foreach ($assets as $asset) {
            $list[] = [
                'id' => $asset->id,
                'path' => $asset->file_path,
                'name' => "[{$asset->media_type}] {$asset->title} ({$asset->file_name})",
                'media_type' => $asset->media_type,
                'url' => $asset->url,
                'frames' => $asset->frame_urls,
                'visual_analysis' => $asset->ai_visual_analysis,
            ];
        }
        $this->availableVideos = $list;
    }

    public function updatedVideoFile(): void
    {
        if (! $this->videoFile) {
            return;
        }

        try {
            $asset = app(MediaVisionService::class)->storeUploadedMedia($this->videoFile);
            $this->selectedMediaAssetId = $asset->id;
            $this->existingVideoPath = $asset->file_path;
            $this->aiVisualAnalysis = (string) ($asset->ai_visual_analysis ?? '');
            $this->loadAvailableVideos();

            $this->statusType = 'success';
            $this->statusMessage = "💾 Media \"{$asset->title}\" ({$asset->media_type}) otomatis tersimpan ke Storage Library & siap dibaca AI Vision!";
        } catch (\Throwable $e) {
            // Keep standard fallback in saveDraft if immediate storage fails
        }
    }

    public function updatedExistingVideoPath(string $path): void
    {
        if ($path === '') {
            $this->selectedMediaAssetId = null;
            $this->aiVisualAnalysis = '';

            return;
        }

        $asset = app(MediaVisionService::class)->resolveOrCreateAssetFromPath($path);
        if ($asset) {
            $this->selectedMediaAssetId = $asset->id;
            $this->aiVisualAnalysis = (string) ($asset->ai_visual_analysis ?? '');
        }
    }

    public function analyzeCurrentMediaWithVision(OpenRouterService $openRouterService, MediaVisionService $visionService): void
    {
        $asset = $this->selectedMediaAssetId
            ? MediaAsset::find($this->selectedMediaAssetId)
            : ($this->existingVideoPath ? $visionService->resolveOrCreateAssetFromPath($this->existingVideoPath) : null);

        if (! $asset && $this->videoFile) {
            $asset = $visionService->storeUploadedMedia($this->videoFile);
            $this->selectedMediaAssetId = $asset->id;
            $this->existingVideoPath = $asset->file_path;
            $this->loadAvailableVideos();
        }

        if (! $asset) {
            $this->statusType = 'error';
            $this->statusMessage = 'Pilih atau unggah file Video/Gambar terlebih dahulu di Langkah 1 sebelum menjalankan AI Vision.';

            return;
        }

        try {
            $summary = $openRouterService->analyzeMediaVisual($asset, null, $this->selectedAiProviderId);
            $this->aiVisualAnalysis = $summary;
            $this->loadAvailableVideos();

            $this->statusType = 'success';
            $this->statusMessage = '👁️ AI Vision berhasil membaca isi visual & teks pada media Anda!';
        } catch (\Throwable $e) {
            $this->statusType = 'error';
            $this->statusMessage = 'Gagal menganalisa visual media: ' . $e->getMessage();
        }
    }

    public function updatedDestinationType(string $value): void
    {
        if ($value === AdDraft::DESTINATION_WHATSAPP) {
            $this->objective = AdDraft::OBJECTIVE_ENGAGEMENT;
            $this->ctaType = AdDraft::CTA_WHATSAPP_MESSAGE;
        } else {
            if ($this->objective === AdDraft::OBJECTIVE_ENGAGEMENT) {
                $this->objective = AdDraft::OBJECTIVE_TRAFFIC;
            }
            if ($this->ctaType === AdDraft::CTA_WHATSAPP_MESSAGE) {
                $this->ctaType = AdDraft::CTA_LEARN_MORE;
            }
        }
    }

    public function addMapPin(float $lat, float $lng, string $mode = 'include', int $radius = 10): void
    {
        $radius = max(1, min(80, $radius));

        if ($mode === 'exclude') {
            $idx = count($this->excludedLocations) + 1;
            $this->excludedLocations[] = [
                'uid' => 'exc_' . Str::random(6),
                'latitude' => round($lat, 6),
                'longitude' => round($lng, 6),
                'radius' => $radius,
                'distance_unit' => 'kilometer',
                'name' => "Zona Exclude #{$idx}",
            ];
        } else {
            $idx = count($this->customLocations) + 1;
            $this->customLocations[] = [
                'uid' => 'inc_' . Str::random(6),
                'latitude' => round($lat, 6),
                'longitude' => round($lng, 6),
                'radius' => $radius,
                'distance_unit' => 'kilometer',
                'name' => "Titik Target #{$idx}",
            ];
        }

        $this->dispatch('pins-updated', [
            'customLocations' => $this->customLocations,
            'excludedLocations' => $this->excludedLocations,
        ]);
    }

    public function removeIncludePin(int $index): void
    {
        if (isset($this->customLocations[$index])) {
            array_splice($this->customLocations, $index, 1);
            $this->dispatch('pins-updated', [
                'customLocations' => $this->customLocations,
                'excludedLocations' => $this->excludedLocations,
            ]);
        }
    }

    public function removeExcludePin(int $index): void
    {
        if (isset($this->excludedLocations[$index])) {
            array_splice($this->excludedLocations, $index, 1);
            $this->dispatch('pins-updated', [
                'customLocations' => $this->customLocations,
                'excludedLocations' => $this->excludedLocations,
            ]);
        }
    }

    public function updatedCustomLocations(): void
    {
        foreach ($this->customLocations as $i => $loc) {
            $this->customLocations[$i]['radius'] = max(1, min(80, (int) ($loc['radius'] ?? 10)));
            $this->customLocations[$i]['distance_unit'] = 'kilometer';
        }

        $this->dispatch('pins-updated', [
            'customLocations' => $this->customLocations,
            'excludedLocations' => $this->excludedLocations,
        ]);
    }

    public function updatedExcludedLocations(): void
    {
        foreach ($this->excludedLocations as $i => $loc) {
            $this->excludedLocations[$i]['radius'] = max(1, min(80, (int) ($loc['radius'] ?? 5)));
            $this->excludedLocations[$i]['distance_unit'] = 'kilometer';
        }

        $this->dispatch('pins-updated', [
            'customLocations' => $this->customLocations,
            'excludedLocations' => $this->excludedLocations,
        ]);
    }

    /**
     * Call OpenRouterService + MetaMarketingService to generate Omni-Channel draft parameters & copywriting.
     */
    public function generateWithAi(OpenRouterService $openRouterService, MetaMarketingService $metaService): void
    {
        $this->validate([
            'rawUserPrompt' => 'required|string|min:5',
        ], [
            'rawUserPrompt.required' => 'Tuliskan perintah AI terlebih dahulu.',
        ]);

        try {
            $setting = Setting::current();

            $textCoord = $openRouterService->extractCoordinatesFromText($this->rawUserPrompt);
            if ($textCoord !== null && count($this->customLocations) <= 1) {
                $this->customLocations[0]['latitude'] = $textCoord['latitude'];
                $this->customLocations[0]['longitude'] = $textCoord['longitude'];
            }

            $visionService = app(MediaVisionService::class);
            if ($this->videoFile && empty($this->existingVideoPath)) {
                $uploadedAsset = $visionService->storeUploadedMedia($this->videoFile);
                $this->selectedMediaAssetId = $uploadedAsset->id;
                $this->existingVideoPath = $uploadedAsset->file_path;
                $this->loadAvailableVideos();
            }

            $currentAsset = $this->selectedMediaAssetId
                ? MediaAsset::find($this->selectedMediaAssetId)
                : ($this->existingVideoPath ? $visionService->resolveOrCreateAssetFromPath($this->existingVideoPath) : null);

            if ($currentAsset && empty($currentAsset->ai_visual_analysis)) {
                $this->aiVisualAnalysis = $openRouterService->analyzeMediaVisual($currentAsset, $setting, $this->selectedAiProviderId);
                $currentAsset->refresh();
            }

            $aiResult = $openRouterService->parseAndGenerateDraft(
                $this->rawUserPrompt,
                $this->customLocations,
                $setting,
                $this->selectedAiProviderId,
                $this->existingVideoPath ?: null,
                $currentAsset
            );

            $this->title = $aiResult['title'];
            $this->dailyBudget = (int) $aiResult['daily_budget'];
            $this->ageMin = (int) $aiResult['age_min'];
            $this->ageMax = (int) $aiResult['age_max'];

            $genders = $aiResult['genders'] ?? [];
            if ($genders === [1]) {
                $this->genderSelection = 'male';
            } elseif ($genders === [2]) {
                $this->genderSelection = 'female';
            } else {
                $this->genderSelection = 'all';
            }

            $this->destinationType = $aiResult['destination_type'];
            if (! empty($aiResult['destination_url'])) {
                $this->destinationUrl = $aiResult['destination_url'];
            }
            $this->objective = $aiResult['objective'];
            $this->ctaType = $aiResult['cta_type'];

            if (! empty($aiResult['target_platforms'])) {
                $this->targetPlatforms = $aiResult['target_platforms'];
            }

            $extractedRadius = max(1, min(80, (int) ($aiResult['radius_km'] ?? 10)));
            foreach ($this->customLocations as $idx => $loc) {
                $this->customLocations[$idx]['radius'] = $extractedRadius;
            }

            $this->aiPrimaryText = $aiResult['ai_primary_text'];
            $this->aiHeadline = $aiResult['ai_headline'];
            $this->aiDescription = $aiResult['ai_description'];

            $this->tiktokAdText = $aiResult['tiktok_ad_text'] ?? Str::limit($this->aiHeadline . ' Klik sekarang! #promo', 100, '');
            $this->googleHeadline = $aiResult['google_headline'] ?? Str::limit($this->aiHeadline, 30, '');
            $this->googleLongHeadline = $aiResult['google_long_headline'] ?? Str::limit($this->aiHeadline . ' - Klaim Promo Hari Ini!', 90, '');
            $this->googleDescription = $aiResult['google_description'] ?? Str::limit($this->aiDescription, 90, '');

            $keywords = $aiResult['interest_keywords'] ?? [];
            if (! empty($keywords)) {
                $metaSetting = AdAccount::resolveSettingForPlatform(AdAccount::PROVIDER_META, $this->metaAccountId, $setting);
                $metaInterests = $metaService->searchInterests($keywords, $metaSetting);
                if (! empty($metaInterests)) {
                    $this->interests = array_map(fn ($item) => [
                        'id' => (string) $item['id'],
                        'name' => (string) $item['name'],
                    ], $metaInterests);
                } else {
                    $this->interestSearchQuery = implode(', ', $keywords);
                }
            }

            $this->aiGenerated = true;
            $this->statusType = 'success';
            $this->statusMessage = '✨ Draft parameter targeting & copywriting Omni-Channel (Meta, TikTok & Google Ads) berhasil disusun oleh AI!';

            $this->dispatch('pins-updated', [
                'customLocations' => $this->customLocations,
                'excludedLocations' => $this->excludedLocations,
            ]);
        } catch (\Throwable $e) {
            $this->statusType = 'error';
            $this->statusMessage = 'Gagal menyusun draft AI: ' . $e->getMessage();
        }
    }

    public function regenerateCopyOnly(OpenRouterService $openRouterService): void
    {
        try {
            $tempDraft = new AdDraft([
                'raw_user_prompt' => $this->rawUserPrompt,
                'destination_type' => $this->destinationType,
                'ai_primary_text' => $this->aiPrimaryText,
                'ai_headline' => $this->aiHeadline,
                'ai_description' => $this->aiDescription,
            ]);

            $newCopy = $openRouterService->regenerateCopywriting($tempDraft, null, $this->selectedAiProviderId);
            $this->aiPrimaryText = $newCopy['ai_primary_text'];
            $this->aiHeadline = $newCopy['ai_headline'];
            $this->aiDescription = $newCopy['ai_description'];
            $this->tiktokAdText = $newCopy['tiktok_ad_text'] ?? $this->tiktokAdText;
            $this->googleHeadline = $newCopy['google_headline'] ?? $this->googleHeadline;
            $this->googleLongHeadline = $newCopy['google_long_headline'] ?? $this->googleLongHeadline;
            $this->googleDescription = $newCopy['google_description'] ?? $this->googleDescription;

            $this->statusType = 'success';
            $this->statusMessage = '🔄 Variasi copywriting baru untuk Meta, TikTok & Google Ads berhasil dibuat!';
        } catch (\Throwable $e) {
            $this->statusType = 'error';
            $this->statusMessage = 'Gagal membuat ulang copywriting: ' . $e->getMessage();
        }
    }

    public function searchMetaInterests(MetaMarketingService $metaService): void
    {
        $raw = trim($this->interestSearchQuery);
        if ($raw === '') {
            return;
        }

        $keywords = array_filter(array_map('trim', explode(',', $raw)));
        $results = $metaService->searchInterests($keywords);

        $this->interestSearchResults = $results;

        if (empty($results)) {
            $this->statusType = 'error';
            $this->statusMessage = 'Tidak ditemukan interest dari Meta API (pastikan Meta Access Token sudah diisi di Pengaturan Sistem).';
        }
    }

    public function addInterestItem(string $id, string $name): void
    {
        foreach ($this->interests as $existing) {
            if ((string) $existing['id'] === $id) {
                return;
            }
        }

        $this->interests[] = [
            'id' => $id,
            'name' => $name,
        ];
    }

    public function removeInterestItem(int $index): void
    {
        if (isset($this->interests[$index])) {
            array_splice($this->interests, $index, 1);
        }
    }

    public function saveDraft(bool $publishImmediately = false)
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'dailyBudget' => 'required|integer|min:15000',
            'ageMin' => 'required|integer|min:18|max:65',
            'ageMax' => 'required|integer|min:18|max:65',
            'aiPrimaryText' => 'required|string|min:10',
            'aiHeadline' => 'required|string|max:40',
            'customLocations' => 'required|array|min:1',
            'targetPlatforms' => 'required|array|min:1',
        ], [
            'title.required' => 'Judul kampanye wajib diisi (klik tombol Buat Draft dengan AI terlebih dahulu atau isi manual).',
            'aiPrimaryText.required' => 'Primary Text copywriting wajib diisi.',
            'aiHeadline.required' => 'Headline iklan wajib diisi (maksimal 40 karakter).',
            'customLocations.required' => 'Minimal 1 titik koordinat lokasi harus dipilih pada peta.',
            'targetPlatforms.required' => 'Pilih minimal 1 platform tujuan (Meta Ads, TikTok Ads, atau Google Ads).',
        ]);

        if (! $this->videoFile && empty($this->existingVideoPath)) {
            $this->statusType = 'error';
            $this->statusMessage = 'Silakan unggah file video iklan terlebih dahulu atau pilih video yang tersedia.';

            return null;
        }

        $visionService = app(MediaVisionService::class);

        if ($this->existingVideoPath !== '') {
            $storedVideoPath = $this->existingVideoPath;
        } elseif ($this->videoFile) {
            $asset = $visionService->storeUploadedMedia($this->videoFile);
            $this->selectedMediaAssetId = $asset->id;
            $storedVideoPath = $asset->file_path;
        } else {
            $storedVideoPath = '';
        }

        $linkedAsset = $this->selectedMediaAssetId
            ? MediaAsset::find($this->selectedMediaAssetId)
            : $visionService->resolveOrCreateAssetFromPath($storedVideoPath);

        $genders = match ($this->genderSelection) {
            'male' => [1],
            'female' => [2],
            default => [],
        };

        $cleanIncludeLocations = array_values(array_map(fn ($loc) => [
            'latitude' => round((float) $loc['latitude'], 6),
            'longitude' => round((float) $loc['longitude'], 6),
            'radius' => max(1, min(80, (int) ($loc['radius'] ?? 10))),
            'distance_unit' => 'kilometer',
            'name' => (string) ($loc['name'] ?? 'Titik Target'),
        ], $this->customLocations));

        $cleanExcludeLocations = array_values(array_map(fn ($loc) => [
            'latitude' => round((float) $loc['latitude'], 6),
            'longitude' => round((float) $loc['longitude'], 6),
            'radius' => max(1, min(80, (int) ($loc['radius'] ?? 5))),
            'distance_unit' => 'kilometer',
            'name' => (string) ($loc['name'] ?? 'Zona Exclude'),
        ], $this->excludedLocations));

        $platforms = array_values(array_unique(array_map('strtoupper', $this->targetPlatforms)));

        $metaAccounts = ! empty($this->selectedMetaAccountIds)
            ? array_values(array_filter(array_map('intval', $this->selectedMetaAccountIds)))
            : ($this->metaAccountId ? [(int) $this->metaAccountId] : [null]);

        $tiktokAccounts = ! empty($this->selectedTiktokAccountIds)
            ? array_values(array_filter(array_map('intval', $this->selectedTiktokAccountIds)))
            : ($this->tiktokAccountId ? [(int) $this->tiktokAccountId] : [null]);

        $googleAccounts = ! empty($this->selectedGoogleAccountIds)
            ? array_values(array_filter(array_map('intval', $this->selectedGoogleAccountIds)))
            : ($this->googleAccountId ? [(int) $this->googleAccountId] : [null]);

        $batchCount = max(
            in_array('META', $platforms, true) ? count($metaAccounts) : 1,
            in_array('TIKTOK', $platforms, true) ? count($tiktokAccounts) : 1,
            in_array('GOOGLE', $platforms, true) ? count($googleAccounts) : 1,
            1
        );

        $firstDraft = null;

        for ($i = 0; $i < $batchCount; $i++) {
            $draftPlatforms = [];
            $mAccId = null;
            $tAccId = null;
            $gAccId = null;

            if (in_array('META', $platforms, true) && ($i < count($metaAccounts) || $i === 0)) {
                $draftPlatforms[] = 'META';
                $mAccId = $metaAccounts[$i] ?? ($metaAccounts[0] ?? null);
            }

            if (in_array('TIKTOK', $platforms, true) && ($i < count($tiktokAccounts) || $i === 0)) {
                $draftPlatforms[] = 'TIKTOK';
                $tAccId = $tiktokAccounts[$i] ?? ($tiktokAccounts[0] ?? null);
            }

            if (in_array('GOOGLE', $platforms, true) && ($i < count($googleAccounts) || $i === 0)) {
                $draftPlatforms[] = 'GOOGLE';
                $gAccId = $googleAccounts[$i] ?? ($googleAccounts[0] ?? null);
            }

            if (empty($draftPlatforms)) {
                continue;
            }

            $publishMeta = $publishImmediately && in_array('META', $draftPlatforms, true);
            $publishTiktok = $publishImmediately && in_array('TIKTOK', $draftPlatforms, true);
            $publishGoogle = $publishImmediately && in_array('GOOGLE', $draftPlatforms, true);

            $draftTitle = trim($this->title);
            if ($batchCount > 1) {
                $draftTitle .= ' [Batch #' . ($i + 1) . ']';
            }

            $draft = AdDraft::create([
                'title' => $draftTitle,
                'raw_user_prompt' => trim($this->rawUserPrompt),
                'video_local_path' => $storedVideoPath,
                'media_asset_id' => $linkedAsset?->id,
                'media_type' => $linkedAsset?->media_type ?? 'VIDEO',
                'ai_visual_context' => trim($this->aiVisualAnalysis) ?: ($linkedAsset?->ai_visual_analysis ?? null),
                'target_platforms' => $draftPlatforms,
                'meta_account_id' => $mAccId,
                'tiktok_account_id' => $tAccId,
                'google_account_id' => $gAccId,
                'ai_provider_id' => $this->selectedAiProviderId,
                'objective' => $this->objective,
                'destination_type' => $this->destinationType,
                'destination_url' => trim($this->destinationUrl) ?: null,
                'daily_budget' => (int) $this->dailyBudget,
                'age_min' => (int) $this->ageMin,
                'age_max' => max((int) $this->ageMin, (int) $this->ageMax),
                'genders' => $genders,
                'custom_locations' => $cleanIncludeLocations,
                'excluded_locations' => $cleanExcludeLocations,
                'interests' => array_values($this->interests),
                'ai_primary_text' => trim($this->aiPrimaryText),
                'ai_headline' => Str::limit(trim($this->aiHeadline), 40, ''),
                'ai_description' => trim($this->aiDescription) ?: null,
                'cta_type' => $this->ctaType,
                'status' => $publishMeta ? AdDraft::STATUS_UPLOADING_VIDEO : AdDraft::STATUS_DRAFT,
                'tiktok_ad_text' => Str::limit(trim($this->tiktokAdText ?: $this->aiHeadline), 100, ''),
                'tiktok_location_ids' => [trim($this->tiktokLocationId) ?: '1643084'],
                'tiktok_status' => $publishTiktok ? AdDraft::TIKTOK_STATUS_UPLOADING : AdDraft::TIKTOK_STATUS_DRAFT,
                'google_headline' => Str::limit(trim($this->googleHeadline ?: $this->aiHeadline), 30, ''),
                'google_long_headline' => Str::limit(trim($this->googleLongHeadline ?: $this->aiHeadline), 90, ''),
                'google_description' => Str::limit(trim($this->googleDescription ?: $this->aiDescription), 90, ''),
                'youtube_video_id' => trim($this->youtubeVideoId) ?: null,
                'google_status' => $publishGoogle ? AdDraft::GOOGLE_STATUS_UPLOADING_YT : AdDraft::GOOGLE_STATUS_DRAFT,
            ]);

            if ($publishMeta) {
                PublishMetaAdJob::dispatch($draft->id);
            }
            if ($publishTiktok) {
                PublishTiktokAdJob::dispatch($draft->id);
            }
            if ($publishGoogle) {
                PublishGoogleAdJob::dispatch($draft->id);
            }

            if (! $firstDraft) {
                $firstDraft = $draft;
            }
        }

        return redirect()->route('ads.show', $firstDraft->id);
    }

    public function saveAndPublish()
    {
        return $this->saveDraft(true);
    }

    public function render()
    {
        return view('livewire.ad-studio', [
            'metaAccounts' => AdAccount::where('provider', AdAccount::PROVIDER_META)->where('is_active', true)->orderByDesc('is_default')->get(),
            'tiktokAccounts' => AdAccount::where('provider', AdAccount::PROVIDER_TIKTOK)->where('is_active', true)->orderByDesc('is_default')->get(),
            'googleAccounts' => AdAccount::where('provider', AdAccount::PROVIDER_GOOGLE)->where('is_active', true)->orderByDesc('is_default')->get(),
            'aiProviders' => AiProvider::where('is_active', true)->orderByDesc('is_default')->orderBy('priority')->get(),
        ]);
    }
}
