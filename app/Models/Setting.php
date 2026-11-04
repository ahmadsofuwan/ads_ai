<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'meta_access_token',
        'meta_ad_account_id',
        'meta_page_id',
        'meta_whatsapp_number',
        'meta_pixel_id',
        'tiktok_access_token',
        'tiktok_advertiser_id',
        'tiktok_identity_id',
        'tiktok_identity_type',
        'tiktok_pixel_id',
        'tiktok_default_location_id',
        'google_developer_token',
        'google_client_id',
        'google_client_secret',
        'google_refresh_token',
        'google_customer_id',
        'google_login_customer_id',
        'ai_provider',
        'ai_base_url',
        'openrouter_api_key',
        'openrouter_model',
        'telegram_bot_token',
        'allowed_telegram_chat_ids',
        'default_latitude',
        'default_longitude',
        'deploy_webhook_secret',
        'deploy_branch',
        'last_deployed_at',
        'last_deploy_commit',
        'last_deploy_status',
        'last_deploy_log',
    ];

    protected $casts = [
        'allowed_telegram_chat_ids' => 'array',
        'default_latitude' => 'float',
        'default_longitude' => 'float',
        'last_deployed_at' => 'datetime',
    ];

    /**
     * Automatically ensure meta_ad_account_id starts with act_
     */
    protected function metaAdAccountId(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null || trim($value) === '') {
                    return null;
                }
                $clean = trim($value);

                return str_starts_with($clean, 'act_') ? $clean : 'act_' . $clean;
            },
            set: function (?string $value): ?string {
                if ($value === null || trim($value) === '') {
                    return null;
                }
                $clean = preg_replace('/^act_/i', '', trim($value));

                return 'act_' . $clean;
            }
        );
    }

    /**
     * Automatically strip dashes from Google Ads Customer ID (e.g., 123-456-7890 -> 1234567890)
     */
    protected function googleCustomerId(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? preg_replace('/[^0-9]/', '', $value) : null,
            set: fn (?string $value) => $value ? (preg_replace('/[^0-9]/', '', $value) ?: null) : null,
        );
    }

    protected function googleLoginCustomerId(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? preg_replace('/[^0-9]/', '', $value) : null,
            set: fn (?string $value) => $value ? (preg_replace('/[^0-9]/', '', $value) ?: null) : null,
        );
    }

    /**
     * Retrieve the single configuration record, creating it with defaults if none exists.
     */
    public static function current(): self
    {
        $setting = static::query()->first();

        if (! $setting) {
            $setting = static::query()->create([
                'ai_provider' => 'OPENROUTER',
                'openrouter_model' => 'openai/gpt-4o-mini',
                'tiktok_identity_type' => 'CUSTOMIZED_USER',
                'tiktok_default_location_id' => '1643084',
                'allowed_telegram_chat_ids' => [],
                'default_latitude' => -1.610123,
                'default_longitude' => 103.613120,
            ]);
        }

        return $setting;
    }

    /**
     * Check if a given Telegram chat_id is permitted to use the bot.
     */
    public function isChatAllowed(string|int $chatId): bool
    {
        $allowed = $this->allowed_telegram_chat_ids;

        if (empty($allowed) || ! is_array($allowed)) {
            return true;
        }

        $normalizedAllowed = array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            $allowed
        )));

        if (empty($normalizedAllowed)) {
            return true;
        }

        return in_array(trim((string) $chatId), $normalizedAllowed, true);
    }

    public function hasMetaCredentials(): bool
    {
        if (! empty($this->meta_access_token)
            && ! empty($this->meta_ad_account_id)
            && ! empty($this->meta_page_id)) {
            return true;
        }

        if ($this->exists) {
            $acc = AdAccount::resolveAccountForPlatform(AdAccount::PROVIDER_META);
            return $acc !== null && $acc->hasValidCredentials();
        }

        return false;
    }

    public function hasTiktokCredentials(): bool
    {
        if (! empty($this->tiktok_access_token)
            && ! empty($this->tiktok_advertiser_id)) {
            return true;
        }

        if ($this->exists) {
            $acc = AdAccount::resolveAccountForPlatform(AdAccount::PROVIDER_TIKTOK);
            return $acc !== null && $acc->hasValidCredentials();
        }

        return false;
    }

    public function hasGoogleCredentials(): bool
    {
        if (! empty($this->google_developer_token)
            && ! empty($this->google_client_id)
            && ! empty($this->google_client_secret)
            && ! empty($this->google_refresh_token)
            && ! empty($this->google_customer_id)) {
            return true;
        }

        if ($this->exists) {
            $acc = AdAccount::resolveAccountForPlatform(AdAccount::PROVIDER_GOOGLE);
            return $acc !== null && $acc->hasValidCredentials();
        }

        return false;
    }

    public function hasOpenRouterCredentials(): bool
    {
        if (! empty($this->openrouter_api_key)) {
            return true;
        }

        if ($this->exists) {
            return AiProvider::query()
                ->where('is_active', true)
                ->whereNotNull('api_key')
                ->where('api_key', '!=', '')
                ->exists();
        }

        return false;
    }

    public function hasTelegramCredentials(): bool
    {
        return ! empty($this->telegram_bot_token);
    }
}
