<?php

namespace App\Livewire;

use App\Jobs\PublishGoogleAdJob;
use App\Jobs\PublishMetaAdJob;
use App\Jobs\PublishTiktokAdJob;
use App\Models\AdAccount;
use App\Models\AdDraft;
use App\Models\AiProvider;
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
#[Title('Ringkasan Dashboard Omni-Channel — MetaAds AI Publisher')]
class DashboardOverview extends Component
{
    public array $apiStatus = [
        'meta' => ['ok' => false, 'label' => 'Belum Dicek'],
        'tiktok' => ['ok' => false, 'label' => 'Belum Dicek'],
        'google' => ['ok' => false, 'label' => 'Belum Dicek'],
        'openrouter' => ['ok' => false, 'label' => 'Belum Dicek'],
        'telegram' => ['ok' => false, 'label' => 'Belum Dicek'],
    ];

    public float $todaySpend = 0.0;
    public int $todayClicks = 0;
    public int $todayChats = 0;
    public int $todayImpressions = 0;
    public ?string $flashMessage = null;
    public string $flashType = 'success';

    public function mount(): void
    {
        $setting = Setting::current();

        $metaCount = AdAccount::where('provider', AdAccount::PROVIDER_META)->where('is_active', true)->count();
        $tiktokCount = AdAccount::where('provider', AdAccount::PROVIDER_TIKTOK)->where('is_active', true)->count();
        $googleCount = AdAccount::where('provider', AdAccount::PROVIDER_GOOGLE)->where('is_active', true)->count();
        $aiCount = AiProvider::where('is_active', true)->count();

        $metaResolved = AdAccount::resolveSettingForPlatform(AdAccount::PROVIDER_META, null, $setting);
        $tiktokResolved = AdAccount::resolveSettingForPlatform(AdAccount::PROVIDER_TIKTOK, null, $setting);
        $googleResolved = AdAccount::resolveSettingForPlatform(AdAccount::PROVIDER_GOOGLE, null, $setting);

        $this->apiStatus = [
            'meta' => [
                'ok' => $setting->hasMetaCredentials(),
                'label' => $setting->hasMetaCredentials()
                    ? 'Terkonfigurasi (' . $metaResolved->meta_ad_account_id . ($metaCount > 1 ? " • {$metaCount} Akun" : '') . ')'
                    : 'Kredensial Belum Lengkap',
            ],
            'tiktok' => [
                'ok' => $setting->hasTiktokCredentials(),
                'label' => $setting->hasTiktokCredentials()
                    ? 'Terkonfigurasi (Adv: ' . $tiktokResolved->tiktok_advertiser_id . ($tiktokCount > 1 ? " • {$tiktokCount} Akun" : '') . ')'
                    : 'Kredensial Belum Lengkap',
            ],
            'google' => [
                'ok' => $setting->hasGoogleCredentials(),
                'label' => $setting->hasGoogleCredentials()
                    ? 'Terkonfigurasi (CID: ' . $googleResolved->google_customer_id . ($googleCount > 1 ? " • {$googleCount} Akun" : '') . ')'
                    : 'Kredensial Belum Lengkap',
            ],
            'openrouter' => [
                'ok' => $setting->hasOpenRouterCredentials(),
                'label' => $setting->hasOpenRouterCredentials()
                    ? 'Aktif (' . ($setting->ai_provider ?: 'OPENROUTER') . ' • ' . $setting->openrouter_model . ($aiCount > 0 ? " + {$aiCount} Profil" : '') . ')'
                    : 'Mode Heuristik Lokal (API Key Kosong)',
            ],
            'telegram' => [
                'ok' => $setting->hasTelegramCredentials(),
                'label' => $setting->hasTelegramCredentials()
                    ? 'Token Bot Tersimpan'
                    : 'Token Belum Diisi',
            ],
        ];

        $this->refreshLiveMetrics();
    }

    public function refreshLiveMetrics(): void
    {
        $setting = Setting::current();
        $metaService = app(MetaMarketingService::class);
        $tiktokService = app(TikTokMarketingService::class);
        $googleService = app(GoogleAdsService::class);

        $this->todaySpend = 0.0;
        $this->todayClicks = 0;
        $this->todayChats = 0;
        $this->todayImpressions = 0;

        $publishedDrafts = AdDraft::query()
            ->where(function ($q) {
                $q->whereNotNull('meta_campaign_id')
                    ->orWhereNotNull('tiktok_campaign_id')
                    ->orWhereNotNull('google_campaign_id');
            })
            ->limit(10)
            ->get();

        foreach ($publishedDrafts as $draft) {
            if (! empty($draft->meta_campaign_id)) {
                $mSet = $draft->resolveSettingForPlatform(AdDraft::PLATFORM_META, $setting);
                if ($mSet->hasMetaCredentials()) {
                    $mIns = $metaService->getInsights((string) $draft->meta_campaign_id, $mSet, 'today');
                    $this->todaySpend += (float) ($mIns['spend'] ?? 0);
                    $this->todayClicks += (int) ($mIns['clicks'] ?? 0);
                    $this->todayChats += (int) ($mIns['conversations'] ?? 0);
                    $this->todayImpressions += (int) ($mIns['impressions'] ?? 0);
                }
            }

            if (! empty($draft->tiktok_campaign_id)) {
                $tSet = $draft->resolveSettingForPlatform(AdDraft::PLATFORM_TIKTOK, $setting);
                if ($tSet->hasTiktokCredentials()) {
                    $tIns = $tiktokService->getInsights((string) $draft->tiktok_campaign_id, $tSet, 'today');
                    $this->todaySpend += (float) ($tIns['spend'] ?? 0);
                    $this->todayClicks += (int) ($tIns['clicks'] ?? 0);
                    $this->todayChats += (int) ($tIns['conversations'] ?? 0);
                    $this->todayImpressions += (int) ($tIns['impressions'] ?? 0);
                }
            }

            if (! empty($draft->google_campaign_id)) {
                $gSet = $draft->resolveSettingForPlatform(AdDraft::PLATFORM_GOOGLE, $setting);
                if ($gSet->hasGoogleCredentials()) {
                    $gIns = $googleService->getInsights((string) $draft->google_campaign_id, $gSet, 'today');
                    $this->todaySpend += (float) ($gIns['spend'] ?? 0);
                    $this->todayClicks += (int) ($gIns['clicks'] ?? 0);
                    $this->todayChats += (int) ($gIns['conversations'] ?? 0);
                    $this->todayImpressions += (int) ($gIns['impressions'] ?? 0);
                }
            }
        }
    }

    public function verifyAllConnections(): void
    {
        $setting = Setting::current();
        $metaRes = app(MetaMarketingService::class)->testConnection($setting);
        $tiktokRes = app(TikTokMarketingService::class)->testConnection($setting);
        $googleRes = app(GoogleAdsService::class)->testConnection($setting);
        $orRes = app(OpenRouterService::class)->testConnection($setting);
        $tgRes = app(TelegramBotService::class)->testConnection($setting);

        $this->apiStatus = [
            'meta' => [
                'ok' => (bool) ($metaRes['ok'] ?? false),
                'label' => (string) ($metaRes['message'] ?? ''),
            ],
            'tiktok' => [
                'ok' => (bool) ($tiktokRes['ok'] ?? false),
                'label' => (string) ($tiktokRes['message'] ?? ''),
            ],
            'google' => [
                'ok' => (bool) ($googleRes['ok'] ?? false),
                'label' => (string) ($googleRes['message'] ?? ''),
            ],
            'openrouter' => [
                'ok' => (bool) ($orRes['ok'] ?? false),
                'label' => (string) ($orRes['message'] ?? ''),
            ],
            'telegram' => [
                'ok' => (bool) ($tgRes['ok'] ?? false),
                'label' => (string) ($tgRes['message'] ?? ''),
            ],
        ];

        $this->refreshLiveMetrics();
        $this->flashType = 'success';
        $this->flashMessage = 'Pengecekan koneksi real-time Meta, TikTok, Google Ads, OpenRouter, dan Telegram selesai.';
    }

    public function publishNow(int $draftId): void
    {
        $draft = AdDraft::find($draftId);
        if (! $draft) {
            return;
        }

        $platforms = $draft->platforms;
        $dispatched = [];

        if (in_array(AdDraft::PLATFORM_META, $platforms, true)) {
            $draft->update(['status' => AdDraft::STATUS_UPLOADING_VIDEO, 'error_log' => null]);
            PublishMetaAdJob::dispatch($draft->id, $draft->telegram_chat_id);
            $dispatched[] = 'Meta Ads';
        }

        if (in_array(AdDraft::PLATFORM_TIKTOK, $platforms, true)) {
            $draft->update(['tiktok_status' => AdDraft::TIKTOK_STATUS_UPLOADING, 'tiktok_error_log' => null]);
            PublishTiktokAdJob::dispatch($draft->id, $draft->telegram_chat_id);
            $dispatched[] = 'TikTok Ads';
        }

        if (in_array(AdDraft::PLATFORM_GOOGLE, $platforms, true)) {
            $draft->update(['google_status' => AdDraft::GOOGLE_STATUS_UPLOADING_YT, 'google_error_log' => null]);
            PublishGoogleAdJob::dispatch($draft->id, $draft->telegram_chat_id);
            $dispatched[] = 'Google Ads';
        }

        $this->flashType = 'success';
        $this->flashMessage = 'Job penerbitan (' . implode(', ', $dispatched) . ") untuk \"{$draft->title}\" telah dikirim ke Queue.";
    }

    public function render()
    {
        $totalActive = AdDraft::where('status', AdDraft::STATUS_ACTIVE_ON_META)
            ->orWhere('tiktok_status', AdDraft::TIKTOK_STATUS_ACTIVE)
            ->orWhere('google_status', AdDraft::GOOGLE_STATUS_ACTIVE)
            ->count();

        $totalPaused = AdDraft::where('status', AdDraft::STATUS_PAUSED_ON_META)
            ->orWhere('tiktok_status', AdDraft::TIKTOK_STATUS_PAUSED)
            ->orWhere('google_status', AdDraft::GOOGLE_STATUS_PAUSED)
            ->count();

        $totalDrafts = AdDraft::where('status', AdDraft::STATUS_DRAFT)->count();
        $totalFailed = AdDraft::where('status', AdDraft::STATUS_FAILED)
            ->orWhere('tiktok_status', AdDraft::TIKTOK_STATUS_FAILED)
            ->orWhere('google_status', AdDraft::GOOGLE_STATUS_FAILED)
            ->count();

        $activeDailyBudget = (int) AdDraft::where('status', AdDraft::STATUS_ACTIVE_ON_META)
            ->orWhere('tiktok_status', AdDraft::TIKTOK_STATUS_ACTIVE)
            ->orWhere('google_status', AdDraft::GOOGLE_STATUS_ACTIVE)
            ->sum('daily_budget');

        $recentAds = AdDraft::query()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('livewire.dashboard-overview', [
            'totalActive' => $totalActive,
            'totalPaused' => $totalPaused,
            'totalDrafts' => $totalDrafts,
            'totalFailed' => $totalFailed,
            'activeDailyBudget' => $activeDailyBudget,
            'recentAds' => $recentAds,
        ]);
    }
}
