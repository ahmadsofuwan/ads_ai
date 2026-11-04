<?php

namespace App\Livewire;

use App\Models\AdAccount;
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
#[Title('Pengaturan Multi-Provider & Multi-API — MetaAds AI Publisher')]
class SystemSettings extends Component
{
    // Meta Ads v21.0 (Default / Quick Setting)
    public string $metaAccessToken = '';
    public string $metaAdAccountId = '';
    public string $metaPageId = '';
    public string $metaWhatsappNumber = '';
    public string $metaPixelId = '';

    // TikTok Ads v1.3 (Default / Quick Setting)
    public string $tiktokAccessToken = '';
    public string $tiktokAdvertiserId = '';
    public string $tiktokIdentityId = '';
    public string $tiktokIdentityType = 'CUSTOMIZED_USER';
    public string $tiktokPixelId = '';
    public string $tiktokDefaultLocationId = '1643084';

    // Google Ads v18 & YouTube Data API v3 (Default / Quick Setting)
    public string $googleDeveloperToken = '';
    public string $googleClientId = '';
    public string $googleClientSecret = '';
    public string $googleRefreshToken = '';
    public string $googleCustomerId = '';
    public string $googleLoginCustomerId = '';

    // Primary AI Provider Setting
    public string $aiProvider = 'OPENROUTER';
    public string $aiBaseUrl = '';
    public string $openrouterApiKey = '';
    public string $openrouterModel = 'openai/gpt-4o-mini';

    // Telegram Bot
    public string $telegramBotToken = '';
    public string $allowedTelegramChatIdsText = '';
    public string $webhookDomainUrl = '';

    public float $defaultLatitude = -1.610123;
    public float $defaultLongitude = 103.613120;

    // Multi-Account Ads Form State (ad_accounts)
    public ?int $editingAdAccountId = null;
    public string $accountName = '';
    public string $accountProvider = AdAccount::PROVIDER_META;
    public bool $accountIsDefault = false;
    public bool $accountIsActive = true;

    public string $accMetaAccessToken = '';
    public string $accMetaAdAccountId = '';
    public string $accMetaPageId = '';
    public string $accMetaWhatsappNumber = '';
    public string $accMetaPixelId = '';

    public string $accTiktokAccessToken = '';
    public string $accTiktokAdvertiserId = '';
    public string $accTiktokIdentityId = '';
    public string $accTiktokIdentityType = 'CUSTOMIZED_USER';
    public string $accTiktokPixelId = '';
    public string $accTiktokDefaultLocationId = '1643084';

    public string $accGoogleDeveloperToken = '';
    public string $accGoogleClientId = '';
    public string $accGoogleClientSecret = '';
    public string $accGoogleRefreshToken = '';
    public string $accGoogleCustomerId = '';
    public string $accGoogleLoginCustomerId = '';

    // Multi-Provider AI Form State (ai_providers)
    public ?int $editingAiProviderId = null;
    public string $aiProfileName = '';
    public string $aiProfileProvider = AiProvider::PROVIDER_OPENROUTER;
    public string $aiProfileBaseUrl = '';
    public string $aiProfileApiKey = '';
    public string $aiProfileModel = 'openai/gpt-4o-mini';
    public int $aiProfilePriority = 10;
    public bool $aiProfileIsDefault = false;
    public bool $aiProfileIsActive = true;

    public ?string $feedbackMessage = null;
    public string $feedbackType = 'success';

    public function mount(): void
    {
        $setting = Setting::current();

        $this->metaAccessToken = (string) ($setting->meta_access_token ?? '');
        $this->metaAdAccountId = (string) ($setting->meta_ad_account_id ?? '');
        $this->metaPageId = (string) ($setting->meta_page_id ?? '');
        $this->metaWhatsappNumber = (string) ($setting->meta_whatsapp_number ?? '');
        $this->metaPixelId = (string) ($setting->meta_pixel_id ?? '');

        $this->tiktokAccessToken = (string) ($setting->tiktok_access_token ?? '');
        $this->tiktokAdvertiserId = (string) ($setting->tiktok_advertiser_id ?? '');
        $this->tiktokIdentityId = (string) ($setting->tiktok_identity_id ?? '');
        $this->tiktokIdentityType = (string) ($setting->tiktok_identity_type ?: 'CUSTOMIZED_USER');
        $this->tiktokPixelId = (string) ($setting->tiktok_pixel_id ?? '');
        $this->tiktokDefaultLocationId = (string) ($setting->tiktok_default_location_id ?: '1643084');

        $this->googleDeveloperToken = (string) ($setting->google_developer_token ?? '');
        $this->googleClientId = (string) ($setting->google_client_id ?? '');
        $this->googleClientSecret = (string) ($setting->google_client_secret ?? '');
        $this->googleRefreshToken = (string) ($setting->google_refresh_token ?? '');
        $this->googleCustomerId = (string) ($setting->google_customer_id ?? '');
        $this->googleLoginCustomerId = (string) ($setting->google_login_customer_id ?? '');

        $this->aiProvider = (string) ($setting->ai_provider ?: 'OPENROUTER');
        $this->aiBaseUrl = (string) ($setting->ai_base_url ?? '');
        $this->openrouterApiKey = (string) ($setting->openrouter_api_key ?? '');
        $this->openrouterModel = (string) ($setting->openrouter_model ?: 'openai/gpt-4o-mini');

        $this->telegramBotToken = (string) ($setting->telegram_bot_token ?? '');
        $allowedList = $setting->allowed_telegram_chat_ids ?? [];
        $this->allowedTelegramChatIdsText = is_array($allowedList) ? implode(', ', $allowedList) : '';

        $this->defaultLatitude = (float) ($setting->default_latitude ?: -1.610123);
        $this->defaultLongitude = (float) ($setting->default_longitude ?: 103.613120);

        $this->webhookDomainUrl = rtrim(config('app.url', request()->getSchemeAndHttpHost()), '/') . '/api/telegram/webhook';
    }

    public function updatedAiProfileProvider(string $provider): void
    {
        $prov = strtoupper(trim($provider));
        $this->aiProfileModel = AiProvider::DEFAULT_MODELS[$prov] ?? 'openai/gpt-4o-mini';
        if ($prov !== AiProvider::PROVIDER_CUSTOM) {
            $this->aiProfileBaseUrl = '';
        }
    }

    public function saveSettings(): Setting
    {
        $this->validate([
            'openrouterModel' => 'required|string|max:120',
            'defaultLatitude' => 'required|numeric|between:-90,90',
            'defaultLongitude' => 'required|numeric|between:-180,180',
        ]);

        $chatIds = [];
        if (trim($this->allowedTelegramChatIdsText) !== '') {
            $parts = preg_split('/[\s,;]+/', trim($this->allowedTelegramChatIdsText));
            foreach ($parts as $part) {
                $clean = trim($part);
                if ($clean !== '') {
                    $chatIds[] = $clean;
                }
            }
            $chatIds = array_values(array_unique($chatIds));
        }

        $setting = Setting::current();
        $setting->update([
            'meta_access_token' => trim($this->metaAccessToken) ?: null,
            'meta_ad_account_id' => trim($this->metaAdAccountId) ?: null,
            'meta_page_id' => trim($this->metaPageId) ?: null,
            'meta_whatsapp_number' => trim($this->metaWhatsappNumber) ?: null,
            'meta_pixel_id' => trim($this->metaPixelId) ?: null,

            'tiktok_access_token' => trim($this->tiktokAccessToken) ?: null,
            'tiktok_advertiser_id' => trim($this->tiktokAdvertiserId) ?: null,
            'tiktok_identity_id' => trim($this->tiktokIdentityId) ?: null,
            'tiktok_identity_type' => trim($this->tiktokIdentityType) ?: 'CUSTOMIZED_USER',
            'tiktok_pixel_id' => trim($this->tiktokPixelId) ?: null,
            'tiktok_default_location_id' => trim($this->tiktokDefaultLocationId) ?: '1643084',

            'google_developer_token' => trim($this->googleDeveloperToken) ?: null,
            'google_client_id' => trim($this->googleClientId) ?: null,
            'google_client_secret' => trim($this->googleClientSecret) ?: null,
            'google_refresh_token' => trim($this->googleRefreshToken) ?: null,
            'google_customer_id' => trim($this->googleCustomerId) ?: null,
            'google_login_customer_id' => trim($this->googleLoginCustomerId) ?: null,

            'ai_provider' => strtoupper(trim($this->aiProvider)) ?: 'OPENROUTER',
            'ai_base_url' => trim($this->aiBaseUrl) ?: null,
            'openrouter_api_key' => trim($this->openrouterApiKey) ?: null,
            'openrouter_model' => trim($this->openrouterModel) ?: 'openai/gpt-4o-mini',
            'telegram_bot_token' => trim($this->telegramBotToken) ?: null,
            'allowed_telegram_chat_ids' => $chatIds,
            'default_latitude' => round((float) $this->defaultLatitude, 6),
            'default_longitude' => round((float) $this->defaultLongitude, 6),
        ]);

        $setting->refresh();
        $this->metaAdAccountId = (string) ($setting->meta_ad_account_id ?? '');
        $this->googleCustomerId = (string) ($setting->google_customer_id ?? '');
        $this->googleLoginCustomerId = (string) ($setting->google_login_customer_id ?? '');

        $this->feedbackType = 'success';
        $this->feedbackMessage = '✅ Seluruh pengaturan sistem dan kredensial utama berhasil disimpan!';

        return $setting;
    }

    // =========================================================================
    // MULTI-ACCOUNT ADS API MANAGEMENT (ad_accounts)
    // =========================================================================

    public function resetAdAccountForm(): void
    {
        $this->editingAdAccountId = null;
        $this->accountName = '';
        $this->accountProvider = AdAccount::PROVIDER_META;
        $this->accountIsDefault = false;
        $this->accountIsActive = true;

        $this->accMetaAccessToken = '';
        $this->accMetaAdAccountId = '';
        $this->accMetaPageId = '';
        $this->accMetaWhatsappNumber = '';
        $this->accMetaPixelId = '';

        $this->accTiktokAccessToken = '';
        $this->accTiktokAdvertiserId = '';
        $this->accTiktokIdentityId = '';
        $this->accTiktokIdentityType = 'CUSTOMIZED_USER';
        $this->accTiktokPixelId = '';
        $this->accTiktokDefaultLocationId = '1643084';

        $this->accGoogleDeveloperToken = '';
        $this->accGoogleClientId = '';
        $this->accGoogleClientSecret = '';
        $this->accGoogleRefreshToken = '';
        $this->accGoogleCustomerId = '';
        $this->accGoogleLoginCustomerId = '';
    }

    public function editAdAccount(int $id): void
    {
        $account = AdAccount::find($id);
        if (! $account) {
            return;
        }

        $this->editingAdAccountId = $account->id;
        $this->accountName = (string) $account->name;
        $this->accountProvider = (string) $account->provider;
        $this->accountIsDefault = (bool) $account->is_default;
        $this->accountIsActive = (bool) $account->is_active;

        $this->accMetaAccessToken = (string) ($account->meta_access_token ?? '');
        $this->accMetaAdAccountId = (string) ($account->meta_ad_account_id ?? '');
        $this->accMetaPageId = (string) ($account->meta_page_id ?? '');
        $this->accMetaWhatsappNumber = (string) ($account->meta_whatsapp_number ?? '');
        $this->accMetaPixelId = (string) ($account->meta_pixel_id ?? '');

        $this->accTiktokAccessToken = (string) ($account->tiktok_access_token ?? '');
        $this->accTiktokAdvertiserId = (string) ($account->tiktok_advertiser_id ?? '');
        $this->accTiktokIdentityId = (string) ($account->tiktok_identity_id ?? '');
        $this->accTiktokIdentityType = (string) ($account->tiktok_identity_type ?: 'CUSTOMIZED_USER');
        $this->accTiktokPixelId = (string) ($account->tiktok_pixel_id ?? '');
        $this->accTiktokDefaultLocationId = (string) ($account->tiktok_default_location_id ?: '1643084');

        $this->accGoogleDeveloperToken = (string) ($account->google_developer_token ?? '');
        $this->accGoogleClientId = (string) ($account->google_client_id ?? '');
        $this->accGoogleClientSecret = (string) ($account->google_client_secret ?? '');
        $this->accGoogleRefreshToken = (string) ($account->google_refresh_token ?? '');
        $this->accGoogleCustomerId = (string) ($account->google_customer_id ?? '');
        $this->accGoogleLoginCustomerId = (string) ($account->google_login_customer_id ?? '');
    }

    public function saveAdAccount(): void
    {
        $this->validate([
            'accountName' => 'required|string|max:150',
            'accountProvider' => 'required|in:META,TIKTOK,GOOGLE',
        ], [
            'accountName.required' => 'Nama/Label Akun Iklan wajib diisi (contoh: Meta BM Cabang 1).',
        ]);

        $provider = strtoupper(trim($this->accountProvider));
        $isFirstForProvider = AdAccount::where('provider', $provider)->count() === 0;

        $payload = [
            'name' => trim($this->accountName),
            'provider' => $provider,
            'is_active' => $this->accountIsActive,
            'is_default' => $this->accountIsDefault || $isFirstForProvider,
            'meta_access_token' => trim($this->accMetaAccessToken) ?: null,
            'meta_ad_account_id' => trim($this->accMetaAdAccountId) ?: null,
            'meta_page_id' => trim($this->accMetaPageId) ?: null,
            'meta_whatsapp_number' => trim($this->accMetaWhatsappNumber) ?: null,
            'meta_pixel_id' => trim($this->accMetaPixelId) ?: null,
            'tiktok_access_token' => trim($this->accTiktokAccessToken) ?: null,
            'tiktok_advertiser_id' => trim($this->accTiktokAdvertiserId) ?: null,
            'tiktok_identity_id' => trim($this->accTiktokIdentityId) ?: null,
            'tiktok_identity_type' => trim($this->accTiktokIdentityType) ?: 'CUSTOMIZED_USER',
            'tiktok_pixel_id' => trim($this->accTiktokPixelId) ?: null,
            'tiktok_default_location_id' => trim($this->accTiktokDefaultLocationId) ?: '1643084',
            'google_developer_token' => trim($this->accGoogleDeveloperToken) ?: null,
            'google_client_id' => trim($this->accGoogleClientId) ?: null,
            'google_client_secret' => trim($this->accGoogleClientSecret) ?: null,
            'google_refresh_token' => trim($this->accGoogleRefreshToken) ?: null,
            'google_customer_id' => trim($this->accGoogleCustomerId) ?: null,
            'google_login_customer_id' => trim($this->accGoogleLoginCustomerId) ?: null,
        ];

        if ($this->editingAdAccountId) {
            $account = AdAccount::findOrFail($this->editingAdAccountId);
            $account->update($payload);
        } else {
            $account = AdAccount::create($payload);
        }

        if ($account->is_default) {
            $account->markAsDefault();
        }

        $this->resetAdAccountForm();
        $this->feedbackType = 'success';
        $this->feedbackMessage = "✅ Akun API Iklan \"{$account->name}\" ({$account->provider}) berhasil disimpan!";
    }

    public function setDefaultAdAccount(int $id): void
    {
        $account = AdAccount::find($id);
        if (! $account) {
            return;
        }

        $account->markAsDefault();
        $this->feedbackType = 'success';
        $this->feedbackMessage = "⭐ Akun \"{$account->name}\" dijadikan Default untuk platform {$account->provider}.";
    }

    public function toggleAdAccountActive(int $id): void
    {
        $account = AdAccount::find($id);
        if (! $account) {
            return;
        }

        $account->update(['is_active' => ! $account->is_active]);
    }

    public function deleteAdAccount(int $id): void
    {
        $account = AdAccount::find($id);
        if (! $account) {
            return;
        }

        $name = $account->name;
        $account->delete();

        if ($this->editingAdAccountId === $id) {
            $this->resetAdAccountForm();
        }

        $this->feedbackType = 'success';
        $this->feedbackMessage = "🗑️ Akun API \"{$name}\" berhasil dihapus.";
    }

    public function testSingleAdAccount(
        int $id,
        MetaMarketingService $metaService,
        TikTokMarketingService $tiktokService,
        GoogleAdsService $googleService
    ): void {
        $account = AdAccount::find($id);
        if (! $account) {
            return;
        }

        $virtualSetting = $account->toSetting();

        $result = match ($account->provider) {
            AdAccount::PROVIDER_META => $metaService->testConnection($virtualSetting),
            AdAccount::PROVIDER_TIKTOK => $tiktokService->testConnection($virtualSetting),
            AdAccount::PROVIDER_GOOGLE => $googleService->testConnection($virtualSetting),
            default => ['ok' => false, 'message' => 'Provider tidak dikenali'],
        };

        $account->update([
            'last_tested_at' => now(),
            'last_test_ok' => (bool) ($result['ok'] ?? false),
            'last_test_message' => (string) ($result['message'] ?? ''),
        ]);

        $this->feedbackType = ! empty($result['ok']) ? 'success' : 'error';
        $this->feedbackMessage = (! empty($result['ok']) ? '✅ ' : '❌ ')
            . "[{$account->name}] " . ($result['message'] ?? '');
    }

    // =========================================================================
    // MULTI-PROVIDER AI LLM MANAGEMENT (ai_providers)
    // =========================================================================

    public function resetAiProviderForm(): void
    {
        $this->editingAiProviderId = null;
        $this->aiProfileName = '';
        $this->aiProfileProvider = AiProvider::PROVIDER_OPENROUTER;
        $this->aiProfileBaseUrl = '';
        $this->aiProfileApiKey = '';
        $this->aiProfileModel = 'openai/gpt-4o-mini';
        $this->aiProfilePriority = 10;
        $this->aiProfileIsDefault = false;
        $this->aiProfileIsActive = true;
    }

    public function editAiProvider(int $id): void
    {
        $prov = AiProvider::find($id);
        if (! $prov) {
            return;
        }

        $this->editingAiProviderId = $prov->id;
        $this->aiProfileName = (string) $prov->name;
        $this->aiProfileProvider = (string) $prov->provider;
        $this->aiProfileBaseUrl = (string) ($prov->base_url ?? '');
        $this->aiProfileApiKey = (string) ($prov->api_key ?? '');
        $this->aiProfileModel = (string) $prov->model;
        $this->aiProfilePriority = (int) $prov->priority;
        $this->aiProfileIsDefault = (bool) $prov->is_default;
        $this->aiProfileIsActive = (bool) $prov->is_active;
    }

    public function saveAiProvider(): void
    {
        $this->validate([
            'aiProfileName' => 'required|string|max:120',
            'aiProfileProvider' => 'required|in:OPENROUTER,OPENAI,GEMINI,GROQ,DEEPSEEK,CUSTOM',
            'aiProfileModel' => 'required|string|max:120',
        ], [
            'aiProfileName.required' => 'Nama profil AI Provider wajib diisi.',
        ]);

        $isFirst = AiProvider::count() === 0;
        $providerCode = strtoupper(trim($this->aiProfileProvider));

        $payload = [
            'name' => trim($this->aiProfileName),
            'provider' => $providerCode,
            'base_url' => trim($this->aiProfileBaseUrl) ?: null,
            'api_key' => trim($this->aiProfileApiKey) ?: null,
            'model' => trim($this->aiProfileModel) ?: (AiProvider::DEFAULT_MODELS[$providerCode] ?? 'openai/gpt-4o-mini'),
            'priority' => max(1, (int) $this->aiProfilePriority),
            'is_default' => $this->aiProfileIsDefault || $isFirst,
            'is_active' => $this->aiProfileIsActive,
        ];

        if ($this->editingAiProviderId) {
            $prov = AiProvider::findOrFail($this->editingAiProviderId);
            $prov->update($payload);
        } else {
            $prov = AiProvider::create($payload);
        }

        if ($prov->is_default) {
            $prov->markAsDefault();
        }

        $this->resetAiProviderForm();
        $this->feedbackType = 'success';
        $this->feedbackMessage = "✅ Profil AI Provider \"{$prov->name}\" ({$prov->provider}) berhasil disimpan!";
    }

    public function setDefaultAiProvider(int $id): void
    {
        $prov = AiProvider::find($id);
        if (! $prov) {
            return;
        }

        $prov->markAsDefault();
        $this->feedbackType = 'success';
        $this->feedbackMessage = "⭐ AI Provider \"{$prov->name}\" dijadikan Default utama.";
    }

    public function deleteAiProvider(int $id): void
    {
        $prov = AiProvider::find($id);
        if (! $prov) {
            return;
        }

        $name = $prov->name;
        $prov->delete();

        if ($this->editingAiProviderId === $id) {
            $this->resetAiProviderForm();
        }

        $this->feedbackType = 'success';
        $this->feedbackMessage = "🗑️ Profil AI Provider \"{$name}\" berhasil dihapus.";
    }

    public function testSingleAiProvider(int $id, OpenRouterService $openRouterService): void
    {
        $prov = AiProvider::find($id);
        if (! $prov) {
            return;
        }

        $res = $openRouterService->testProviderConnection($prov);

        $prov->update([
            'last_tested_at' => now(),
            'last_test_ok' => (bool) ($res['ok'] ?? false),
            'last_test_message' => (string) ($res['message'] ?? ''),
        ]);

        $this->feedbackType = ! empty($res['ok']) ? 'success' : 'error';
        $this->feedbackMessage = (! empty($res['ok']) ? '✅ ' : '❌ ') . ($res['message'] ?? '');
    }

    // =========================================================================
    // EXISTING QUICK CONNECTION TESTS
    // =========================================================================

    public function testMetaConnection(MetaMarketingService $metaService): void
    {
        $setting = $this->saveSettings();
        $result = $metaService->testConnection($setting);

        $this->feedbackType = $result['ok'] ? 'success' : 'error';
        $this->feedbackMessage = $result['ok']
            ? '✅ Koneksi Meta API v21.0 Berhasil: ' . $result['message']
            : '❌ Tes Koneksi Meta API Gagal: ' . $result['message'];
    }

    public function testTiktokConnection(TikTokMarketingService $tiktokService): void
    {
        $setting = $this->saveSettings();
        $result = $tiktokService->testConnection($setting);

        $this->feedbackType = $result['ok'] ? 'success' : 'error';
        $this->feedbackMessage = $result['ok']
            ? '✅ Koneksi TikTok Business API v1.3 Berhasil: ' . $result['message']
            : '❌ Tes Koneksi TikTok API Gagal: ' . $result['message'];
    }

    public function testGoogleConnection(GoogleAdsService $googleService): void
    {
        $setting = $this->saveSettings();
        $result = $googleService->testConnection($setting);

        $this->feedbackType = $result['ok'] ? 'success' : 'error';
        $this->feedbackMessage = $result['ok']
            ? '✅ Koneksi Google Ads API v18 Berhasil: ' . $result['message']
            : '❌ Tes Koneksi Google Ads API Gagal: ' . $result['message'];
    }

    public function testOpenRouterConnection(OpenRouterService $openRouterService): void
    {
        $setting = $this->saveSettings();
        $result = $openRouterService->testConnection($setting);

        $this->feedbackType = $result['ok'] ? 'success' : 'error';
        $this->feedbackMessage = $result['ok']
            ? '✅ Koneksi AI Provider Berhasil: ' . $result['message']
            : '❌ Tes Koneksi AI Provider Gagal: ' . $result['message'];
    }

    public function installTelegramWebhook(TelegramBotService $telegramService): void
    {
        $setting = $this->saveSettings();

        if (trim($this->webhookDomainUrl) === '') {
            $this->feedbackType = 'error';
            $this->feedbackMessage = 'Masukkan URL Domain HTTPS atau Ngrok terlebih dahulu.';

            return;
        }

        try {
            $botCheck = $telegramService->testConnection($setting);
            if (! $botCheck['ok']) {
                $this->feedbackType = 'error';
                $this->feedbackMessage = '❌ ' . $botCheck['message'];

                return;
            }

            $res = $telegramService->setWebhook($this->webhookDomainUrl, $setting);
            $this->webhookDomainUrl = $res['webhook_url'];

            $this->feedbackType = 'success';
            $this->feedbackMessage = "✅ Webhook Telegram berhasil dipasang ke {$res['webhook_url']} ({$botCheck['message']})!";
        } catch (\Throwable $e) {
            $this->feedbackType = 'error';
            $this->feedbackMessage = '❌ Gagal memasang Webhook Telegram: ' . $e->getMessage();
        }
    }

    public function setDefaultCoordsFromMap(float $lat, float $lng): void
    {
        $this->defaultLatitude = round($lat, 6);
        $this->defaultLongitude = round($lng, 6);
    }

    public function render()
    {
        return view('livewire.system-settings', [
            'adAccounts' => AdAccount::query()
                ->orderBy('provider')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'aiProviders' => AiProvider::query()
                ->orderByDesc('is_default')
                ->orderBy('priority')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
