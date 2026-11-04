<?php

namespace App\Livewire;

use App\Jobs\PublishGoogleAdJob;
use App\Jobs\PublishMetaAdJob;
use App\Jobs\PublishTiktokAdJob;
use App\Models\AdAccount;
use App\Models\AdDraft;
use App\Models\Setting;
use App\Services\GoogleAdsService;
use App\Services\MetaMarketingService;
use App\Services\OpenRouterService;
use App\Services\TelegramBotService;
use App\Services\TikTokMarketingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Detail Kampanye Omni-Channel — MetaAds AI Publisher')]
class AdCampaignDetail extends Component
{
    public AdDraft $draft;

    public string $datePreset = 'today';
    public array $insights = [
        'spend' => 0.0,
        'impressions' => 0,
        'clicks' => 0,
        'cpc' => 0.0,
        'ctr' => 0.0,
        'conversations' => 0,
    ];

    // Editable fields
    public string $title = '';
    public int $dailyBudget = 50000;
    public int $ageMin = 21;
    public int $ageMax = 50;
    public string $destinationType = 'WHATSAPP';
    public string $destinationUrl = '';
    public string $objective = 'OUTCOME_ENGAGEMENT';
    public string $ctaType = 'WHATSAPP_MESSAGE';

    // Meta Copy
    public string $aiPrimaryText = '';
    public string $aiHeadline = '';
    public string $aiDescription = '';

    // TikTok Copy
    public string $tiktokAdText = '';

    // Google Copy
    public string $googleHeadline = '';
    public string $googleLongHeadline = '';
    public string $googleDescription = '';
    public string $youtubeVideoId = '';

    // Assigned Multi-Account IDs
    public ?int $metaAccountId = null;
    public ?int $tiktokAccountId = null;
    public ?int $googleAccountId = null;

    public ?string $flashMessage = null;
    public string $flashType = 'success';

    public function mount(AdDraft $adDraft): void
    {
        $this->draft = $adDraft;
        $this->syncEditableFields();
        $this->loadInsights();
    }

    protected function syncEditableFields(): void
    {
        $this->title = (string) $this->draft->title;
        $this->dailyBudget = (int) $this->draft->daily_budget;
        $this->ageMin = (int) $this->draft->age_min;
        $this->ageMax = (int) $this->draft->age_max;
        $this->destinationType = (string) $this->draft->destination_type;
        $this->destinationUrl = (string) ($this->draft->destination_url ?? '');
        $this->objective = (string) $this->draft->objective;
        $this->ctaType = (string) $this->draft->cta_type;

        $this->aiPrimaryText = (string) $this->draft->ai_primary_text;
        $this->aiHeadline = (string) $this->draft->ai_headline;
        $this->aiDescription = (string) ($this->draft->ai_description ?? '');

        $this->tiktokAdText = (string) ($this->draft->tiktok_ad_text ?? '');
        $this->googleHeadline = (string) ($this->draft->google_headline ?? '');
        $this->googleLongHeadline = (string) ($this->draft->google_long_headline ?? '');
        $this->googleDescription = (string) ($this->draft->google_description ?? '');
        $this->youtubeVideoId = (string) ($this->draft->youtube_video_id ?? '');

        $this->metaAccountId = $this->draft->meta_account_id ? (int) $this->draft->meta_account_id : null;
        $this->tiktokAccountId = $this->draft->tiktok_account_id ? (int) $this->draft->tiktok_account_id : null;
        $this->googleAccountId = $this->draft->google_account_id ? (int) $this->draft->google_account_id : null;
    }

    public function updatedDatePreset(): void
    {
        $this->loadInsights();
    }

    public function loadInsights(): void
    {
        $this->draft->refresh();
        $setting = Setting::current();

        $totalSpend = 0.0;
        $totalImpressions = 0;
        $totalClicks = 0;
        $totalChats = 0;

        if (! empty($this->draft->meta_campaign_id)) {
            $metaSetting = $this->draft->resolveSettingForPlatform(AdDraft::PLATFORM_META, $setting);
            if ($metaSetting->hasMetaCredentials()) {
                $m = app(MetaMarketingService::class)->getInsights((string) $this->draft->meta_campaign_id, $metaSetting, $this->datePreset);
                $totalSpend += (float) ($m['spend'] ?? 0);
                $totalImpressions += (int) ($m['impressions'] ?? 0);
                $totalClicks += (int) ($m['clicks'] ?? 0);
                $totalChats += (int) ($m['conversations'] ?? 0);
            }
        }

        if (! empty($this->draft->tiktok_campaign_id)) {
            $tiktokSetting = $this->draft->resolveSettingForPlatform(AdDraft::PLATFORM_TIKTOK, $setting);
            if ($tiktokSetting->hasTiktokCredentials()) {
                $t = app(TikTokMarketingService::class)->getInsights((string) $this->draft->tiktok_campaign_id, $tiktokSetting, $this->datePreset);
                $totalSpend += (float) ($t['spend'] ?? 0);
                $totalImpressions += (int) ($t['impressions'] ?? 0);
                $totalClicks += (int) ($t['clicks'] ?? 0);
                $totalChats += (int) ($t['conversations'] ?? 0);
            }
        }

        if (! empty($this->draft->google_campaign_id)) {
            $googleSetting = $this->draft->resolveSettingForPlatform(AdDraft::PLATFORM_GOOGLE, $setting);
            if ($googleSetting->hasGoogleCredentials()) {
                $g = app(GoogleAdsService::class)->getInsights((string) $this->draft->google_campaign_id, $googleSetting, $this->datePreset);
                $totalSpend += (float) ($g['spend'] ?? 0);
                $totalImpressions += (int) ($g['impressions'] ?? 0);
                $totalClicks += (int) ($g['clicks'] ?? 0);
                $totalChats += (int) ($g['conversations'] ?? 0);
            }
        }

        $this->insights = [
            'spend' => $totalSpend,
            'impressions' => $totalImpressions,
            'clicks' => $totalClicks,
            'cpc' => $totalClicks > 0 ? round($totalSpend / $totalClicks, 2) : 0.0,
            'ctr' => $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0.0,
            'conversations' => $totalChats,
        ];
    }

    public function saveChanges(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'dailyBudget' => 'required|integer|min:15000',
            'ageMin' => 'required|integer|min:18|max:65',
            'ageMax' => 'required|integer|min:18|max:65',
            'aiPrimaryText' => 'required|string',
            'aiHeadline' => 'required|string|max:40',
        ]);

        $this->draft->update([
            'title' => trim($this->title),
            'daily_budget' => (int) $this->dailyBudget,
            'age_min' => (int) $this->ageMin,
            'age_max' => max((int) $this->ageMin, (int) $this->ageMax),
            'destination_type' => $this->destinationType,
            'destination_url' => trim($this->destinationUrl) ?: null,
            'objective' => $this->objective,
            'cta_type' => $this->ctaType,
            'ai_primary_text' => trim($this->aiPrimaryText),
            'ai_headline' => trim($this->aiHeadline),
            'ai_description' => trim($this->aiDescription) ?: null,
            'tiktok_ad_text' => trim($this->tiktokAdText) ?: null,
            'google_headline' => trim($this->googleHeadline) ?: null,
            'google_long_headline' => trim($this->googleLongHeadline) ?: null,
            'google_description' => trim($this->googleDescription) ?: null,
            'youtube_video_id' => trim($this->youtubeVideoId) ?: null,
            'meta_account_id' => $this->metaAccountId ?: null,
            'tiktok_account_id' => $this->tiktokAccountId ?: null,
            'google_account_id' => $this->googleAccountId ?: null,
        ]);

        $this->draft->refresh();
        $this->flashType = 'success';
        $this->flashMessage = 'Perubahan parameter dan copywriting iklan Omni-Channel berhasil disimpan.';
    }

    public function regenerateCopywriting(OpenRouterService $openRouterService): void
    {
        try {
            $newCopy = $openRouterService->regenerateCopywriting($this->draft);
            $this->aiPrimaryText = $newCopy['ai_primary_text'];
            $this->aiHeadline = $newCopy['ai_headline'];
            $this->aiDescription = $newCopy['ai_description'];
            $this->tiktokAdText = $newCopy['tiktok_ad_text'] ?? $this->tiktokAdText;
            $this->googleHeadline = $newCopy['google_headline'] ?? $this->googleHeadline;
            $this->googleLongHeadline = $newCopy['google_long_headline'] ?? $this->googleLongHeadline;
            $this->googleDescription = $newCopy['google_description'] ?? $this->googleDescription;

            $this->saveChanges();

            $this->flashType = 'success';
            $this->flashMessage = 'Copywriting Meta, TikTok & Google Ads berhasil diperbarui oleh AI.';
        } catch (\Throwable $e) {
            $this->flashType = 'error';
            $this->flashMessage = 'Gagal membuat ulang copywriting: ' . $e->getMessage();
        }
    }

    // META CONTROLS
    public function toggleMetaStatus(): void
    {
        $this->draft->refresh();
        if (empty($this->draft->meta_campaign_id)) {
            return;
        }

        try {
            $targetStatus = $this->draft->status === AdDraft::STATUS_ACTIVE_ON_META ? 'PAUSED' : 'ACTIVE';
            app(MetaMarketingService::class)->updateStatus($this->draft, $targetStatus);
            $this->draft->refresh();
            $this->flashType = 'success';
            $this->flashMessage = "Status Meta Ads berhasil diubah menjadi {$targetStatus}.";
        } catch (\Throwable $e) {
            $this->flashType = 'error';
            $this->flashMessage = 'Gagal mengubah status Meta API: ' . $e->getMessage();
        }
    }

    public function retryPublishQueue(): void
    {
        $this->saveChanges();
        $this->draft->update(['status' => AdDraft::STATUS_UPLOADING_VIDEO, 'error_log' => null]);
        PublishMetaAdJob::dispatch($this->draft->id, $this->draft->telegram_chat_id);
        $this->draft->refresh();
        $this->flashType = 'success';
        $this->flashMessage = '🚀 PublishMetaAdJob telah dikirim ke Queue latar belakang.';
    }

    public function retryPublishSync(MetaMarketingService $metaService, TelegramBotService $telegramService): void
    {
        $this->saveChanges();
        $job = new PublishMetaAdJob($this->draft->id, $this->draft->telegram_chat_id);
        $job->handle($metaService, $telegramService);
        $this->draft->refresh();

        $this->flashType = $this->draft->status === AdDraft::STATUS_FAILED ? 'error' : 'success';
        $this->flashMessage = $this->draft->status === AdDraft::STATUS_FAILED
            ? 'Penerbitan Meta Ads gagal. Periksa Log Error Meta di bawah.'
            : '✅ Kampanye berhasil diterbitkan ke Meta Ads (PAUSED)!';
    }

    // TIKTOK CONTROLS
    public function toggleTiktokStatus(): void
    {
        $this->draft->refresh();
        if (empty($this->draft->tiktok_campaign_id)) {
            return;
        }

        try {
            $targetStatus = $this->draft->tiktok_status === AdDraft::TIKTOK_STATUS_ACTIVE ? 'PAUSED' : 'ACTIVE';
            app(TikTokMarketingService::class)->updateStatus($this->draft, $targetStatus);
            $this->draft->refresh();
            $this->flashType = 'success';
            $this->flashMessage = "Status TikTok Ads berhasil diubah menjadi {$targetStatus}.";
        } catch (\Throwable $e) {
            $this->flashType = 'error';
            $this->flashMessage = 'Gagal mengubah status TikTok API: ' . $e->getMessage();
        }
    }

    public function retryTiktokQueue(): void
    {
        $this->saveChanges();
        $this->draft->update(['tiktok_status' => AdDraft::TIKTOK_STATUS_UPLOADING, 'tiktok_error_log' => null]);
        PublishTiktokAdJob::dispatch($this->draft->id, $this->draft->telegram_chat_id);
        $this->draft->refresh();
        $this->flashType = 'success';
        $this->flashMessage = '🎵 PublishTiktokAdJob telah dikirim ke Queue latar belakang.';
    }

    public function retryTiktokSync(TikTokMarketingService $tiktokService, TelegramBotService $telegramService): void
    {
        $this->saveChanges();
        $job = new PublishTiktokAdJob($this->draft->id, $this->draft->telegram_chat_id);
        $job->handle($tiktokService, $telegramService);
        $this->draft->refresh();

        $this->flashType = $this->draft->tiktok_status === AdDraft::TIKTOK_STATUS_FAILED ? 'error' : 'success';
        $this->flashMessage = $this->draft->tiktok_status === AdDraft::TIKTOK_STATUS_FAILED
            ? 'Penerbitan TikTok Ads gagal. Periksa Log Error TikTok di bawah.'
            : '✅ Kampanye berhasil diterbitkan ke TikTok Ads (PAUSED)!';
    }

    // GOOGLE CONTROLS
    public function toggleGoogleStatus(): void
    {
        $this->draft->refresh();
        if (empty($this->draft->google_campaign_id)) {
            return;
        }

        try {
            $targetStatus = $this->draft->google_status === AdDraft::GOOGLE_STATUS_ACTIVE ? 'PAUSED' : 'ACTIVE';
            app(GoogleAdsService::class)->updateStatus($this->draft, $targetStatus);
            $this->draft->refresh();
            $this->flashType = 'success';
            $this->flashMessage = "Status Google Ads berhasil diubah menjadi {$targetStatus}.";
        } catch (\Throwable $e) {
            $this->flashType = 'error';
            $this->flashMessage = 'Gagal mengubah status Google Ads API: ' . $e->getMessage();
        }
    }

    public function retryGoogleQueue(): void
    {
        $this->saveChanges();
        $this->draft->update(['google_status' => AdDraft::GOOGLE_STATUS_UPLOADING_YT, 'google_error_log' => null]);
        PublishGoogleAdJob::dispatch($this->draft->id, $this->draft->telegram_chat_id);
        $this->draft->refresh();
        $this->flashType = 'success';
        $this->flashMessage = '▶️ PublishGoogleAdJob telah dikirim ke Queue latar belakang.';
    }

    public function retryGoogleSync(GoogleAdsService $googleService, TelegramBotService $telegramService): void
    {
        $this->saveChanges();
        $job = new PublishGoogleAdJob($this->draft->id, $this->draft->telegram_chat_id);
        $job->handle($googleService, $telegramService);
        $this->draft->refresh();

        $this->flashType = $this->draft->google_status === AdDraft::GOOGLE_STATUS_FAILED ? 'error' : 'success';
        $this->flashMessage = $this->draft->google_status === AdDraft::GOOGLE_STATUS_FAILED
            ? 'Penerbitan Google Ads gagal. Periksa Log Error Google di bawah.'
            : '✅ Kampanye berhasil diterbitkan ke Google Ads (PAUSED)!';
    }

    public function render()
    {
        $this->draft->refresh();

        return view('livewire.ad-campaign-detail', [
            'metaAccounts' => AdAccount::where('provider', AdAccount::PROVIDER_META)->where('is_active', true)->get(),
            'tiktokAccounts' => AdAccount::where('provider', AdAccount::PROVIDER_TIKTOK)->where('is_active', true)->get(),
            'googleAccounts' => AdAccount::where('provider', AdAccount::PROVIDER_GOOGLE)->where('is_active', true)->get(),
        ]);
    }
}
