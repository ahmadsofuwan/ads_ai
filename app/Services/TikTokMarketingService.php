<?php

namespace App\Services;

use App\Models\AdAccount;
use App\Models\AdDraft;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TikTokMarketingService
{
    public const BASE_URL = 'https://business-api.tiktok.com/open_api/v1.3';

    /**
     * 1. Test connection to TikTok Marketing API v1.3 and retrieve Advertiser details.
     */
    public function testConnection(?Setting $setting = null): array
    {
        $setting = $this->resolveTiktokSetting($setting);

        if (empty($setting->tiktok_access_token) || empty($setting->tiktok_advertiser_id)) {
            return [
                'ok' => false,
                'message' => 'TikTok Access Token atau Advertiser ID belum dikonfigurasi.',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Access-Token' => (string) $setting->tiktok_access_token,
            ])->timeout(30)->get(self::BASE_URL . '/advertiser/info/', [
                'advertiser_ids' => json_encode([(string) $setting->tiktok_advertiser_id]),
                'fields' => json_encode(['advertiser_id', 'name', 'currency', 'timezone', 'status']),
            ]);

            if (! $response->successful() || (int) $response->json('code', -1) !== 0) {
                return [
                    'ok' => false,
                    'message' => $this->formatTiktokError($response, 'Advertiser Info'),
                ];
            }

            $info = $response->json('data.list.0') ?? [];

            return [
                'ok' => true,
                'advertiser_id' => $info['advertiser_id'] ?? $setting->tiktok_advertiser_id,
                'name' => $info['name'] ?? 'TikTok Advertiser',
                'currency' => $info['currency'] ?? 'IDR',
                'status' => $info['status'] ?? 'STATUS_ENABLE',
                'message' => sprintf(
                    'Terhubung ke TikTok Ads: %s (%s - %s)',
                    $info['name'] ?? 'Advertiser',
                    $info['advertiser_id'] ?? $setting->tiktok_advertiser_id,
                    $info['currency'] ?? 'IDR'
                ),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal menghubungi TikTok Business API: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 2. Upload local video file to TikTok Ads Library.
     * Calls POST /file/video/ad/upload/
     *
     * @return array{video_id: string, cover_url: ?string}
     */
    public function uploadVideo(string $localFilePath, ?Setting $setting = null): array
    {
        $setting = $this->requireValidTiktokSettings($setting);

        if (! file_exists($localFilePath) || ! is_readable($localFilePath)) {
            throw new RuntimeException("File video lokal tidak ditemukan: {$localFilePath}");
        }

        $videoSignature = md5_file($localFilePath) ?: md5($localFilePath . time());
        $stream = fopen($localFilePath, 'r');

        if ($stream === false) {
            throw new RuntimeException("Gagal membuka stream file video untuk TikTok: {$localFilePath}");
        }

        try {
            $response = Http::withHeaders([
                'Access-Token' => (string) $setting->tiktok_access_token,
            ])
                ->timeout(600)
                ->connectTimeout(30)
                ->attach('video_file', $stream, basename($localFilePath))
                ->post(self::BASE_URL . '/file/video/ad/upload/', [
                    'advertiser_id' => (string) $setting->tiktok_advertiser_id,
                    'upload_type' => 'UPLOAD_BY_FILE',
                    'video_signature' => $videoSignature,
                    'file_name' => pathinfo($localFilePath, PATHINFO_FILENAME) . '_' . time() . '.mp4',
                ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $response->successful() || (int) $response->json('code', -1) !== 0) {
            throw new RuntimeException($this->formatTiktokError($response, 'Upload Video TikTok'));
        }

        $data = $response->json('data');
        $firstItem = isset($data[0]) && is_array($data[0]) ? $data[0] : (is_array($data) ? $data : []);

        $videoId = (string) ($firstItem['video_id'] ?? $response->json('data.video_id') ?? '');
        if ($videoId === '') {
            throw new RuntimeException('TikTok API tidak mengembalikan video_id setelah unggah video.');
        }

        $coverUrl = $firstItem['video_cover_url'] ?? $response->json('data.video_cover_url') ?? null;

        return [
            'video_id' => $videoId,
            'cover_url' => $coverUrl,
        ];
    }

    /**
     * 3. Resolve or upload a cover thumbnail image_id required by TikTok Ad creative.
     */
    public function resolveCoverImageId(string $videoId, ?string $fallbackCoverUrl = null, ?Setting $setting = null): string
    {
        $setting = $this->requireValidTiktokSettings($setting);

        $coverUrl = $fallbackCoverUrl;

        // Poll video info up to 10 attempts if cover URL is not yet generated
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            if (! empty($coverUrl)) {
                break;
            }

            $infoResp = Http::withHeaders([
                'Access-Token' => (string) $setting->tiktok_access_token,
            ])->timeout(30)->get(self::BASE_URL . '/file/video/ad/info/', [
                'advertiser_id' => (string) $setting->tiktok_advertiser_id,
                'video_ids' => json_encode([$videoId]),
            ]);

            if ($infoResp->successful() && (int) $infoResp->json('code', -1) === 0) {
                $videoInfo = $infoResp->json('data.list.0') ?? [];
                $coverUrl = $videoInfo['video_cover_url'] ?? ($videoInfo['poster_url'] ?? null);
                if (! empty($coverUrl)) {
                    break;
                }
            }

            if ($attempt < 10) {
                sleep(3);
            }
        }

        if (! empty($coverUrl)) {
            $imgResp = Http::withHeaders([
                'Access-Token' => (string) $setting->tiktok_access_token,
            ])->timeout(45)->post(self::BASE_URL . '/file/image/ad/upload/', [
                'advertiser_id' => (string) $setting->tiktok_advertiser_id,
                'upload_type' => 'UPLOAD_BY_URL',
                'image_url' => $coverUrl,
                'file_name' => 'cover_' . $videoId . '_' . time() . '.jpg',
            ]);

            if ($imgResp->successful() && (int) $imgResp->json('code', -1) === 0) {
                $imageId = (string) ($imgResp->json('data.image_id') ?? '');
                if ($imageId !== '') {
                    return $imageId;
                }
            }
        }

        return 'cover_' . $videoId;
    }

    /**
     * 4. Create Campaign on TikTok Ads in DISABLE (Paused) state.
     * Calls POST /campaign/create/
     */
    public function createCampaign(AdDraft $draft, ?Setting $setting = null): string
    {
        $setting = $this->requireValidTiktokSettings($setting, $draft);

        $objectiveType = ($draft->objective === AdDraft::OBJECTIVE_SALES && ! empty($setting->tiktok_pixel_id))
            ? 'WEB_CONVERSIONS'
            : 'TRAFFIC';

        $response = Http::withHeaders([
            'Access-Token' => (string) $setting->tiktok_access_token,
        ])->timeout(45)->post(self::BASE_URL . '/campaign/create/', [
            'advertiser_id' => (string) $setting->tiktok_advertiser_id,
            'campaign_name' => mb_substr($draft->title . ' (TikTok)', 0, 512),
            'objective_type' => $objectiveType,
            'budget_mode' => 'BUDGET_MODE_INFINITE',
            'operation_status' => 'DISABLE',
        ]);

        if (! $response->successful() || (int) $response->json('code', -1) !== 0) {
            throw new RuntimeException($this->formatTiktokError($response, 'Create Campaign TikTok'));
        }

        $campaignId = (string) ($response->json('data.campaign_id') ?? '');
        if ($campaignId === '') {
            throw new RuntimeException('TikTok API tidak mengembalikan campaign_id.');
        }

        return $campaignId;
    }

    /**
     * 5. Create AdGroup on TikTok Ads in DISABLE (Paused) state.
     * Calls POST /adgroup/create/
     */
    public function createAdGroup(AdDraft $draft, ?Setting $setting = null): string
    {
        $setting = $this->requireValidTiktokSettings($setting, $draft);

        if (empty($draft->tiktok_campaign_id)) {
            throw new RuntimeException('tiktok_campaign_id belum tersedia saat membuat AdGroup TikTok.');
        }

        $locationIds = ! empty($draft->tiktok_location_ids) && is_array($draft->tiktok_location_ids)
            ? array_values(array_map('strval', $draft->tiktok_location_ids))
            : [(string) ($setting->tiktok_default_location_id ?: '1643084')];

        $ageGroups = $this->mapAgeRangeToTiktokGroups((int) $draft->age_min, (int) $draft->age_max);

        $gender = 'GENDER_UNLIMITED';
        if ($draft->genders === [1]) {
            $gender = 'GENDER_MALE';
        } elseif ($draft->genders === [2]) {
            $gender = 'GENDER_FEMALE';
        }

        $payload = [
            'advertiser_id' => (string) $setting->tiktok_advertiser_id,
            'campaign_id' => (string) $draft->tiktok_campaign_id,
            'adgroup_name' => mb_substr($draft->title . ' - AdGroup', 0, 512),
            'promotion_type' => 'WEBSITE',
            'placement_type' => 'PLACEMENT_TYPE_AUTOMATIC',
            'location_ids' => $locationIds,
            'age_groups' => $ageGroups,
            'gender' => $gender,
            'budget_mode' => 'BUDGET_MODE_DAY',
            'budget' => max(200000, (int) $draft->daily_budget), // TikTok IDR minimum daily adgroup budget is 200,000 IDR
            'schedule_type' => 'SCHEDULE_FROM_NOW',
            'schedule_start_time' => now()->utc()->format('Y-m-d H:i:s'),
            'optimization_goal' => 'CLICK',
            'billing_event' => 'CPC',
            'pacing' => 'PACING_MODE_SMOOTH',
            'operation_status' => 'DISABLE',
        ];

        $response = Http::withHeaders([
            'Access-Token' => (string) $setting->tiktok_access_token,
        ])->timeout(45)->post(self::BASE_URL . '/adgroup/create/', $payload);

        if (! $response->successful() || (int) $response->json('code', -1) !== 0) {
            throw new RuntimeException($this->formatTiktokError($response, 'Create AdGroup TikTok'));
        }

        $adgroupId = (string) ($response->json('data.adgroup_id') ?? '');
        if ($adgroupId === '') {
            throw new RuntimeException('TikTok API tidak mengembalikan adgroup_id.');
        }

        return $adgroupId;
    }

    /**
     * 6. Create Ad on TikTok Ads in DISABLE (Paused) state.
     * Calls POST /ad/create/
     */
    public function createAd(AdDraft $draft, ?Setting $setting = null): string
    {
        $setting = $this->requireValidTiktokSettings($setting, $draft);

        if (empty($draft->tiktok_adgroup_id) || empty($draft->tiktok_video_id)) {
            throw new RuntimeException('tiktok_adgroup_id dan tiktok_video_id wajib tersedia sebelum membuat Ad TikTok.');
        }

        $destinationUrl = $draft->resolveDestinationUrl($setting);
        $adText = trim((string) ($draft->tiktok_ad_text ?: $draft->ai_headline));
        $adText = mb_substr($adText, 0, 100);

        $cta = match ($draft->cta_type) {
            AdDraft::CTA_SHOP_NOW => 'SHOP_NOW',
            AdDraft::CTA_WHATSAPP_MESSAGE => 'CONTACT_US',
            default => 'LEARN_MORE',
        };

        $creative = [
            'ad_name' => mb_substr($draft->title . ' - Video Ad', 0, 512),
            'ad_format' => 'SINGLE_VIDEO',
            'video_id' => (string) $draft->tiktok_video_id,
            'ad_text' => $adText,
            'call_to_action' => $cta,
            'landing_page_url' => $destinationUrl,
            'operation_status' => 'DISABLE',
        ];

        if (! empty($draft->tiktok_image_id)) {
            $creative['image_ids'] = [(string) $draft->tiktok_image_id];
        }

        if (! empty($setting->tiktok_identity_id)) {
            $creative['identity_id'] = (string) $setting->tiktok_identity_id;
            $creative['identity_type'] = (string) ($setting->tiktok_identity_type ?: 'CUSTOMIZED_USER');
        }

        $response = Http::withHeaders([
            'Access-Token' => (string) $setting->tiktok_access_token,
        ])->timeout(60)->post(self::BASE_URL . '/ad/create/', [
            'advertiser_id' => (string) $setting->tiktok_advertiser_id,
            'adgroup_id' => (string) $draft->tiktok_adgroup_id,
            'creatives' => [$creative],
        ]);

        if (! $response->successful() || (int) $response->json('code', -1) !== 0) {
            throw new RuntimeException($this->formatTiktokError($response, 'Create Ad TikTok'));
        }

        $adId = (string) ($response->json('data.ad_ids.0') ?? $response->json('data.ad_id') ?? '');
        if ($adId === '') {
            throw new RuntimeException('TikTok API tidak mengembalikan ad_id.');
        }

        return $adId;
    }

    /**
     * 7. Update Campaign, AdGroup, and Ad status on TikTok Ads ('ACTIVE' => 'ENABLE', 'PAUSED' => 'DISABLE').
     */
    public function updateStatus(AdDraft $draft, string $newStatus, ?Setting $setting = null): array
    {
        $setting = $this->requireValidTiktokSettings($setting, $draft);
        $isEnable = strtoupper(trim($newStatus)) === 'ACTIVE' || strtoupper(trim($newStatus)) === 'ENABLE';
        $operationStatus = $isEnable ? 'ENABLE' : 'DISABLE';

        if (empty($draft->tiktok_campaign_id)) {
            throw new RuntimeException('Kampanye ini belum memiliki tiktok_campaign_id di TikTok Ads.');
        }

        $headers = ['Access-Token' => (string) $setting->tiktok_access_token];
        $advId = (string) $setting->tiktok_advertiser_id;

        $campResp = Http::withHeaders($headers)->timeout(30)->post(self::BASE_URL . '/campaign/status/update/', [
            'advertiser_id' => $advId,
            'campaign_ids' => [(string) $draft->tiktok_campaign_id],
            'operation_status' => $operationStatus,
        ]);

        if (! $campResp->successful() || (int) $campResp->json('code', -1) !== 0) {
            throw new RuntimeException($this->formatTiktokError($campResp, 'Update Status Campaign TikTok'));
        }

        if (! empty($draft->tiktok_adgroup_id)) {
            Http::withHeaders($headers)->timeout(30)->post(self::BASE_URL . '/adgroup/status/update/', [
                'advertiser_id' => $advId,
                'adgroup_ids' => [(string) $draft->tiktok_adgroup_id],
                'operation_status' => $operationStatus,
            ]);
        }

        if (! empty($draft->tiktok_ad_id)) {
            Http::withHeaders($headers)->timeout(30)->post(self::BASE_URL . '/ad/status/update/', [
                'advertiser_id' => $advId,
                'ad_ids' => [(string) $draft->tiktok_ad_id],
                'operation_status' => $operationStatus,
            ]);
        }

        $draft->update([
            'tiktok_status' => $isEnable
                ? AdDraft::TIKTOK_STATUS_ACTIVE
                : AdDraft::TIKTOK_STATUS_PAUSED,
        ]);

        return [
            'ok' => true,
            'operation_status' => $operationStatus,
            'tiktok_status' => $draft->tiktok_status,
        ];
    }

    /**
     * 8. Fetch integrated campaign report metrics from TikTok API.
     */
    public function getInsights(string $campaignId, ?Setting $setting = null, string $datePreset = 'today'): array
    {
        $setting = $this->resolveTiktokSetting($setting);

        $defaults = [
            'spend' => 0.0,
            'impressions' => 0,
            'clicks' => 0,
            'cpc' => 0.0,
            'ctr' => 0.0,
            'conversations' => 0,
        ];

        if (empty($campaignId) || empty($setting->tiktok_access_token) || empty($setting->tiktok_advertiser_id)) {
            return $defaults;
        }

        [$startDate, $endDate] = match ($datePreset) {
            'yesterday' => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
            'last_7d' => [now()->subDays(6)->toDateString(), now()->toDateString()],
            'maximum' => [now()->subDays(29)->toDateString(), now()->toDateString()],
            default => [now()->toDateString(), now()->toDateString()],
        };

        try {
            $response = Http::withHeaders([
                'Access-Token' => (string) $setting->tiktok_access_token,
            ])->timeout(30)->get(self::BASE_URL . '/report/integrated/get/', [
                'advertiser_id' => (string) $setting->tiktok_advertiser_id,
                'report_type' => 'BASIC',
                'data_level' => 'AUCTION_CAMPAIGN',
                'dimensions' => json_encode(['campaign_id']),
                'metrics' => json_encode(['spend', 'impressions', 'clicks', 'cpc', 'ctr', 'conversion']),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'filtering' => json_encode([
                    [
                        'field_name' => 'campaign_ids',
                        'filter_type' => 'IN',
                        'filter_value' => json_encode([$campaignId]),
                    ],
                ]),
            ]);

            if (! $response->successful() || (int) $response->json('code', -1) !== 0) {
                return $defaults;
            }

            $metrics = $response->json('data.list.0.metrics');
            if (! is_array($metrics)) {
                return $defaults;
            }

            return [
                'spend' => (float) ($metrics['spend'] ?? 0),
                'impressions' => (int) ($metrics['impressions'] ?? 0),
                'clicks' => (int) ($metrics['clicks'] ?? 0),
                'cpc' => (float) ($metrics['cpc'] ?? 0),
                'ctr' => (float) ($metrics['ctr'] ?? 0),
                'conversations' => (int) ($metrics['conversion'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return $defaults;
        }
    }

    /**
     * Map age_min and age_max to TikTok age group buckets.
     */
    public function mapAgeRangeToTiktokGroups(int $ageMin, int $ageMax): array
    {
        $buckets = [
            'AGE_18_24' => [18, 24],
            'AGE_25_34' => [25, 34],
            'AGE_35_44' => [35, 44],
            'AGE_45_54' => [45, 54],
            'AGE_55_100' => [55, 100],
        ];

        $selected = [];
        foreach ($buckets as $code => [$min, $max]) {
            if ($ageMax >= $min && $ageMin <= $max) {
                $selected[] = $code;
            }
        }

        return ! empty($selected) ? $selected : ['AGE_25_34', 'AGE_35_44'];
    }

    protected function resolveTiktokSetting(?Setting $setting = null, ?AdDraft $draft = null): Setting
    {
        if ($setting !== null && ! empty($setting->tiktok_access_token) && ! empty($setting->tiktok_advertiser_id)) {
            return $setting;
        }

        if ($draft !== null) {
            return $draft->resolveSettingForPlatform(AdDraft::PLATFORM_TIKTOK, $setting);
        }

        return AdAccount::resolveSettingForPlatform(AdAccount::PROVIDER_TIKTOK, null, $setting);
    }

    protected function requireValidTiktokSettings(?Setting $setting = null, ?AdDraft $draft = null): Setting
    {
        $setting = $this->resolveTiktokSetting($setting, $draft);

        if (empty($setting->tiktok_access_token)) {
            throw new RuntimeException('TikTok Access Token belum diatur di menu Pengaturan Sistem.');
        }

        if (empty($setting->tiktok_advertiser_id)) {
            throw new RuntimeException('TikTok Advertiser ID belum diatur di menu Pengaturan Sistem.');
        }

        return $setting;
    }

    protected function formatTiktokError(Response $response, string $context): string
    {
        $code = $response->json('code');
        $message = $response->json('message') ?? $response->body();
        $requestId = $response->json('request_id');

        $str = "[{$context}] TikTok API Error";
        if ($code !== null) {
            $str .= " (Code {$code})";
        }
        $str .= ": {$message}";
        if ($requestId) {
            $str .= " (request_id: {$requestId})";
        }

        return $str;
    }
}
