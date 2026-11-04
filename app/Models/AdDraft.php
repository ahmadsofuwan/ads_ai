<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AdDraft extends Model
{
    public const PLATFORM_META = 'META';
    public const PLATFORM_TIKTOK = 'TIKTOK';
    public const PLATFORM_GOOGLE = 'GOOGLE';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_UPLOADING_VIDEO = 'UPLOADING_VIDEO';
    public const STATUS_PUBLISHING_TO_META = 'PUBLISHING_TO_META';
    public const STATUS_PAUSED_ON_META = 'PAUSED_ON_META';
    public const STATUS_ACTIVE_ON_META = 'ACTIVE_ON_META';
    public const STATUS_FAILED = 'FAILED';

    public const TIKTOK_STATUS_DRAFT = 'DRAFT';
    public const TIKTOK_STATUS_UPLOADING = 'UPLOADING_VIDEO';
    public const TIKTOK_STATUS_PUBLISHING = 'PUBLISHING_TO_TIKTOK';
    public const TIKTOK_STATUS_PAUSED = 'PAUSED_ON_TIKTOK';
    public const TIKTOK_STATUS_ACTIVE = 'ACTIVE_ON_TIKTOK';
    public const TIKTOK_STATUS_FAILED = 'FAILED';

    public const GOOGLE_STATUS_DRAFT = 'DRAFT';
    public const GOOGLE_STATUS_UPLOADING_YT = 'UPLOADING_YOUTUBE';
    public const GOOGLE_STATUS_PUBLISHING = 'PUBLISHING_TO_GOOGLE';
    public const GOOGLE_STATUS_PAUSED = 'PAUSED_ON_GOOGLE';
    public const GOOGLE_STATUS_ACTIVE = 'ACTIVE_ON_GOOGLE';
    public const GOOGLE_STATUS_FAILED = 'FAILED';

    public const OBJECTIVE_ENGAGEMENT = 'OUTCOME_ENGAGEMENT';
    public const OBJECTIVE_TRAFFIC = 'OUTCOME_TRAFFIC';
    public const OBJECTIVE_SALES = 'OUTCOME_SALES';

    public const DESTINATION_WHATSAPP = 'WHATSAPP';
    public const DESTINATION_WEBSITE = 'WEBSITE';

    public const CTA_WHATSAPP_MESSAGE = 'WHATSAPP_MESSAGE';
    public const CTA_LEARN_MORE = 'LEARN_MORE';
    public const CTA_SHOP_NOW = 'SHOP_NOW';

    protected $table = 'ad_drafts';

    protected $fillable = [
        'telegram_chat_id',
        'title',
        'raw_user_prompt',
        'video_local_path',
        'media_asset_id',
        'media_type',
        'ai_visual_context',
        'target_platforms',
        'meta_account_id',
        'tiktok_account_id',
        'google_account_id',
        'ai_provider_id',
        'meta_video_id',
        'thumbnail_url',
        'objective',
        'destination_type',
        'destination_url',
        'daily_budget',
        'age_min',
        'age_max',
        'genders',
        'custom_locations',
        'excluded_locations',
        'interests',
        'ai_primary_text',
        'ai_headline',
        'ai_description',
        'cta_type',
        'meta_campaign_id',
        'meta_adset_id',
        'meta_creative_id',
        'meta_ad_id',
        'status',
        'error_log',
        // TikTok Ads
        'tiktok_ad_text',
        'tiktok_location_ids',
        'tiktok_video_id',
        'tiktok_image_id',
        'tiktok_campaign_id',
        'tiktok_adgroup_id',
        'tiktok_ad_id',
        'tiktok_status',
        'tiktok_error_log',
        // Google Ads
        'google_headline',
        'google_long_headline',
        'google_description',
        'youtube_video_id',
        'google_video_asset_resource',
        'google_budget_resource',
        'google_campaign_id',
        'google_adgroup_id',
        'google_ad_id',
        'google_status',
        'google_error_log',
    ];

    protected $casts = [
        'daily_budget' => 'integer',
        'age_min' => 'integer',
        'age_max' => 'integer',
        'target_platforms' => 'array',
        'genders' => 'array',
        'custom_locations' => 'array',
        'excluded_locations' => 'array',
        'interests' => 'array',
        'tiktok_location_ids' => 'array',
    ];

    public function getPlatformsAttribute(): array
    {
        $platforms = $this->target_platforms;
        if (empty($platforms) || ! is_array($platforms)) {
            return [self::PLATFORM_META];
        }

        return array_values(array_unique(array_map('strtoupper', $platforms)));
    }

    public function hasPlatform(string $platform): bool
    {
        return in_array(strtoupper($platform), $this->platforms, true);
    }

    public function getFormattedBudgetAttribute(): string
    {
        return 'Rp ' . number_format((int) $this->daily_budget, 0, ',', '.');
    }

    public function getGenderLabelAttribute(): string
    {
        $genders = $this->genders ?? [];
        if (empty($genders) || (in_array(1, $genders) && in_array(2, $genders))) {
            return 'Semua Gender (Pria & Wanita)';
        }
        if (in_array(1, $genders)) {
            return 'Pria Saja';
        }
        if (in_array(2, $genders)) {
            return 'Wanita Saja';
        }

        return 'Semua Gender';
    }

    public function getVideoUrlAttribute(): ?string
    {
        if (empty($this->video_local_path)) {
            return null;
        }

        if (str_starts_with($this->video_local_path, 'http://') || str_starts_with($this->video_local_path, 'https://')) {
            return $this->video_local_path;
        }

        $relativePath = preg_replace('#^public/#', '', $this->video_local_path);

        return Storage::disk('public')->url($relativePath);
    }

    public function getAbsoluteVideoPath(): string
    {
        $relativePath = preg_replace('#^public/#', '', $this->video_local_path);
        $publicDiskPath = Storage::disk('public')->path($relativePath);

        if (file_exists($publicDiskPath)) {
            return $publicDiskPath;
        }

        $storageAppPath = storage_path('app/public/' . ltrim($relativePath, '/'));
        if (file_exists($storageAppPath)) {
            return $storageAppPath;
        }

        return $this->video_local_path;
    }

    public function resolveDestinationUrl(?Setting $setting = null): string
    {
        if (! empty($this->destination_url)) {
            return $this->destination_url;
        }

        $setting = $setting ?? Setting::current();

        if ($this->destination_type === self::DESTINATION_WHATSAPP) {
            $wa = preg_replace('/[^0-9]/', '', (string) $setting->meta_whatsapp_number);
            if (str_starts_with($wa, '0')) {
                $wa = '62' . substr($wa, 1);
            }

            if (! empty($wa)) {
                $encodedText = rawurlencode('Halo, saya tertarik dengan info di iklan ' . $this->title);
                return "https://wa.me/{$wa}?text={$encodedText}";
            }

            return 'https://api.whatsapp.com/';
        }

        return config('app.url', 'https://example.com');
    }

    public function isPublishedOnMeta(): bool
    {
        return ! empty($this->meta_campaign_id)
            && in_array($this->status, [self::STATUS_PAUSED_ON_META, self::STATUS_ACTIVE_ON_META], true);
    }

    public function isPublishedOnTiktok(): bool
    {
        return ! empty($this->tiktok_campaign_id)
            && in_array($this->tiktok_status, [self::TIKTOK_STATUS_PAUSED, self::TIKTOK_STATUS_ACTIVE], true);
    }

    public function isPublishedOnGoogle(): bool
    {
        return ! empty($this->google_campaign_id)
            && in_array($this->google_status, [self::GOOGLE_STATUS_PAUSED, self::GOOGLE_STATUS_ACTIVE], true);
    }

    public function metaAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'meta_account_id');
    }

    public function tiktokAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'tiktok_account_id');
    }

    public function googleAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'google_account_id');
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    /**
     * Resolve a Setting instance populated with the specific AdAccount assigned to this draft
     * (or the default active AdAccount for that platform, falling back to Setting::current()).
     */
    public function resolveSettingForPlatform(string $platform, ?Setting $baseSetting = null): Setting
    {
        $platform = strtoupper(trim($platform));

        $accountId = match ($platform) {
            self::PLATFORM_META => $this->meta_account_id ? (int) $this->meta_account_id : null,
            self::PLATFORM_TIKTOK => $this->tiktok_account_id ? (int) $this->tiktok_account_id : null,
            self::PLATFORM_GOOGLE => $this->google_account_id ? (int) $this->google_account_id : null,
            default => null,
        };

        return AdAccount::resolveSettingForPlatform($platform, $accountId, $baseSetting);
    }
}
