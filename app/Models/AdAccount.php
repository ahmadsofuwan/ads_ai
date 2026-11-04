<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class AdAccount extends Model
{
    public const PROVIDER_META = 'META';
    public const PROVIDER_TIKTOK = 'TIKTOK';
    public const PROVIDER_GOOGLE = 'GOOGLE';

    protected $table = 'ad_accounts';

    protected $fillable = [
        'name',
        'provider',
        'is_default',
        'is_active',
        // Meta Ads
        'meta_access_token',
        'meta_ad_account_id',
        'meta_page_id',
        'meta_whatsapp_number',
        'meta_pixel_id',
        // TikTok Ads
        'tiktok_access_token',
        'tiktok_advertiser_id',
        'tiktok_identity_id',
        'tiktok_identity_type',
        'tiktok_pixel_id',
        'tiktok_default_location_id',
        // Google Ads
        'google_developer_token',
        'google_client_id',
        'google_client_secret',
        'google_refresh_token',
        'google_customer_id',
        'google_login_customer_id',
        // Status
        'last_tested_at',
        'last_test_ok',
        'last_test_message',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'last_test_ok' => 'boolean',
        'last_tested_at' => 'datetime',
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

    public function hasValidCredentials(): bool
    {
        return match (strtoupper((string) $this->provider)) {
            self::PROVIDER_META => ! empty($this->meta_access_token)
                && ! empty($this->meta_ad_account_id)
                && ! empty($this->meta_page_id),
            self::PROVIDER_TIKTOK => ! empty($this->tiktok_access_token)
                && ! empty($this->tiktok_advertiser_id),
            self::PROVIDER_GOOGLE => ! empty($this->google_developer_token)
                && ! empty($this->google_client_id)
                && ! empty($this->google_client_secret)
                && ! empty($this->google_refresh_token)
                && ! empty($this->google_customer_id),
            default => false,
        };
    }

    public function getIdentifierLabelAttribute(): string
    {
        return match (strtoupper((string) $this->provider)) {
            self::PROVIDER_META => ($this->meta_ad_account_id ?: 'No AdAccount') . ($this->meta_page_id ? " • Page: {$this->meta_page_id}" : ''),
            self::PROVIDER_TIKTOK => 'Adv ID: ' . ($this->tiktok_advertiser_id ?: 'Belum diisi'),
            self::PROVIDER_GOOGLE => 'Customer ID: ' . ($this->google_customer_id ?: 'Belum diisi') . ($this->google_login_customer_id ? " (MCC: {$this->google_login_customer_id})" : ''),
            default => (string) $this->provider,
        };
    }

    public function getProviderBadgeLabelAttribute(): string
    {
        return match (strtoupper((string) $this->provider)) {
            self::PROVIDER_META => '📘 Meta Ads',
            self::PROVIDER_TIKTOK => '🎵 TikTok Ads',
            self::PROVIDER_GOOGLE => '▶️ Google Ads',
            default => (string) $this->provider,
        };
    }

    /**
     * Mark this account as the default for its provider.
     */
    public function markAsDefault(): void
    {
        static::query()
            ->where('provider', strtoupper((string) $this->provider))
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true, 'is_active' => true]);
    }

    /**
     * Convert this AdAccount into an in-memory Setting instance so all Marketing Services work seamlessly.
     */
    public function toSetting(?Setting $baseSetting = null): Setting
    {
        $base = $baseSetting ?? Setting::current();
        $virtual = $base->replicate();

        $provider = strtoupper((string) $this->provider);

        if ($provider === self::PROVIDER_META) {
            $virtual->meta_access_token = $this->meta_access_token;
            $virtual->setRawAttributes(array_merge($virtual->getAttributes(), [
                'meta_ad_account_id' => $this->meta_ad_account_id,
            ]));
            $virtual->meta_page_id = $this->meta_page_id;
            if (! empty($this->meta_whatsapp_number)) {
                $virtual->meta_whatsapp_number = $this->meta_whatsapp_number;
            }
            if (! empty($this->meta_pixel_id)) {
                $virtual->meta_pixel_id = $this->meta_pixel_id;
            }
        } elseif ($provider === self::PROVIDER_TIKTOK) {
            $virtual->tiktok_access_token = $this->tiktok_access_token;
            $virtual->tiktok_advertiser_id = $this->tiktok_advertiser_id;
            $virtual->tiktok_identity_id = $this->tiktok_identity_id;
            $virtual->tiktok_identity_type = $this->tiktok_identity_type ?: 'CUSTOMIZED_USER';
            $virtual->tiktok_pixel_id = $this->tiktok_pixel_id;
            $virtual->tiktok_default_location_id = $this->tiktok_default_location_id ?: '1643084';
        } elseif ($provider === self::PROVIDER_GOOGLE) {
            $virtual->google_developer_token = $this->google_developer_token;
            $virtual->google_client_id = $this->google_client_id;
            $virtual->google_client_secret = $this->google_client_secret;
            $virtual->google_refresh_token = $this->google_refresh_token;
            $virtual->setRawAttributes(array_merge($virtual->getAttributes(), [
                'google_customer_id' => $this->google_customer_id,
                'google_login_customer_id' => $this->google_login_customer_id,
            ]));
        }

        return $virtual;
    }

    /**
     * Find the specified AdAccount or the active default AdAccount for a given platform.
     */
    public static function resolveAccountForPlatform(string $provider, ?int $accountId = null): ?self
    {
        $provider = strtoupper(trim($provider));

        if ($accountId !== null && $accountId > 0) {
            $specific = static::query()
                ->where('provider', $provider)
                ->find($accountId);

            if ($specific) {
                return $specific;
            }
        }

        return static::query()
            ->where('provider', $provider)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /**
     * Resolve a Setting instance hydrated with the appropriate AdAccount credentials for a platform,
     * falling back to Setting::current() if no valid AdAccount is found.
     */
    public static function resolveSettingForPlatform(string $provider, ?int $accountId = null, ?Setting $baseSetting = null): Setting
    {
        $base = $baseSetting ?? Setting::current();
        $account = static::resolveAccountForPlatform($provider, $accountId);

        if ($account && $account->hasValidCredentials()) {
            return $account->toSetting($base);
        }

        return $base;
    }
}
