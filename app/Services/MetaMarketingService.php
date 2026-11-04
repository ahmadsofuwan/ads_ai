<?php

namespace App\Services;

use App\Models\AdAccount;
use App\Models\AdDraft;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaMarketingService
{
    public const BASE_URL = 'https://graph.facebook.com/v21.0';

    /**
     * 1. Test connection to Meta Graph API v21.0 and return Ad Account details.
     */
    public function testConnection(?Setting $setting = null): array
    {
        $setting = $this->resolveMetaSetting($setting);

        if (empty($setting->meta_access_token) || empty($setting->meta_ad_account_id)) {
            return [
                'ok' => false,
                'message' => 'Meta Access Token atau Ad Account ID belum dikonfigurasi.',
            ];
        }

        try {
            $adAccountId = $setting->meta_ad_account_id;
            $response = Http::timeout(30)
                ->connectTimeout(15)
                ->get(self::BASE_URL . "/{$adAccountId}", [
                    'fields' => 'id,name,account_id,account_status,currency,amount_spent,timezone_name',
                    'access_token' => $setting->meta_access_token,
                ]);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'message' => $this->formatMetaError($response, 'Tes Koneksi Ad Account'),
                ];
            }

            $data = $response->json();

            return [
                'ok' => true,
                'account_id' => $data['id'] ?? $adAccountId,
                'account_name' => $data['name'] ?? 'Meta Ad Account',
                'currency' => $data['currency'] ?? 'IDR',
                'account_status' => $data['account_status'] ?? 1,
                'timezone' => $data['timezone_name'] ?? 'Asia/Jakarta',
                'message' => sprintf(
                    'Terhubung ke Ad Account: %s (%s - %s)',
                    $data['name'] ?? 'Tanpa Nama',
                    $data['id'] ?? $adAccountId,
                    $data['currency'] ?? 'IDR'
                ),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal menghubungi Meta Graph API: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 2. Search interests from Meta Targeting Search API.
     * Calls GET /search?type=adinterest&q={keyword}&locale=id_ID
     *
     * @param  array<int, string>  $keywords
     * @return array<int, array{id: string, name: string, audience_size_lower_bound?: int, audience_size_upper_bound?: int}>
     */
    public function searchInterests(array $keywords, ?Setting $setting = null): array
    {
        $setting = $this->resolveMetaSetting($setting);

        if (empty($setting->meta_access_token)) {
            return [];
        }

        $results = [];
        $seenIds = [];

        foreach ($keywords as $keyword) {
            $keyword = trim((string) $keyword);
            if ($keyword === '') {
                continue;
            }

            try {
                $response = Http::timeout(30)
                    ->connectTimeout(15)
                    ->get(self::BASE_URL . '/search', [
                        'type' => 'adinterest',
                        'q' => $keyword,
                        'locale' => 'id_ID',
                        'limit' => 5,
                        'access_token' => $setting->meta_access_token,
                    ]);

                if ($response->successful()) {
                    $items = $response->json('data') ?? [];
                    foreach ($items as $item) {
                        $id = (string) ($item['id'] ?? '');
                        $name = (string) ($item['name'] ?? '');
                        if ($id !== '' && $name !== '' && ! isset($seenIds[$id])) {
                            $seenIds[$id] = true;
                            $results[] = [
                                'id' => $id,
                                'name' => $name,
                                'topic' => $item['topic'] ?? null,
                                'audience_size_lower_bound' => $item['audience_size_lower_bound'] ?? null,
                                'audience_size_upper_bound' => $item['audience_size_upper_bound'] ?? null,
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Continue searching remaining keywords even if one keyword fails
                continue;
            }
        }

        return array_slice($results, 0, 12);
    }

    /**
     * 3. Upload local video file to Meta Ads via multipart stream.
     * Calls POST /act_{id}/advideos
     */
    public function uploadVideo(string $localFilePath, ?Setting $setting = null): string
    {
        $setting = $this->requireValidMetaSettings($setting);
        $adAccountId = $setting->meta_ad_account_id;

        if (! file_exists($localFilePath) || ! is_readable($localFilePath)) {
            throw new RuntimeException("File video lokal tidak ditemukan atau tidak dapat dibaca pada path: {$localFilePath}");
        }

        $stream = fopen($localFilePath, 'r');
        if ($stream === false) {
            throw new RuntimeException("Gagal membuka stream file video: {$localFilePath}");
        }

        try {
            $response = Http::timeout(600)
                ->connectTimeout(30)
                ->attach('source', $stream, basename($localFilePath))
                ->post(self::BASE_URL . "/{$adAccountId}/advideos", [
                    'access_token' => $setting->meta_access_token,
                    'title' => pathinfo($localFilePath, PATHINFO_FILENAME),
                ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->formatMetaError($response, 'Upload Video (advideos)'));
        }

        $videoId = (string) ($response->json('id') ?? '');
        if ($videoId === '') {
            throw new RuntimeException('Meta API tidak mengembalikan video_id setelah unggah video. Response: ' . $response->body());
        }

        return $videoId;
    }

    /**
     * 4. Poll GET /{videoId}?fields=status,thumbnails,picture until video_status is 'ready' and return primary thumbnail URL.
     */
    public function waitForVideoAndGetThumbnail(
        string $videoId,
        ?Setting $setting = null,
        int $maxAttempts = 36,
        int $sleepSeconds = 5
    ): string {
        $setting = $this->requireValidMetaSettings($setting);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = Http::timeout(45)
                ->connectTimeout(15)
                ->get(self::BASE_URL . "/{$videoId}", [
                    'fields' => 'status,thumbnails,picture',
                    'access_token' => $setting->meta_access_token,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException($this->formatMetaError($response, "Polling Status Video ({$videoId})"));
            }

            $data = $response->json();
            $videoStatus = strtolower((string) ($data['status']['video_status'] ?? 'processing'));

            if ($videoStatus === 'error') {
                $errDesc = $data['status']['processing_phase']['errors'][0]['message']
                    ?? json_encode($data['status'] ?? []);
                throw new RuntimeException("Proses encoding video di Meta gagal (status=error): {$errDesc}");
            }

            if ($videoStatus === 'ready') {
                $thumbnails = $data['thumbnails']['data'] ?? [];
                $preferredUrl = null;

                foreach ($thumbnails as $thumb) {
                    if (! empty($thumb['is_preferred']) && ! empty($thumb['uri'])) {
                        $preferredUrl = (string) $thumb['uri'];
                        break;
                    }
                }

                if (! $preferredUrl && ! empty($thumbnails[0]['uri'])) {
                    $preferredUrl = (string) $thumbnails[0]['uri'];
                }

                if (! $preferredUrl && ! empty($data['picture'])) {
                    $preferredUrl = (string) $data['picture'];
                }

                if ($preferredUrl) {
                    return $preferredUrl;
                }
            }

            if ($attempt < $maxAttempts) {
                sleep($sleepSeconds);
            }
        }

        throw new RuntimeException("Timeout menunggu encoding video ({$videoId}) selesai di Meta Ads setelah " . ($maxAttempts * $sleepSeconds) . ' detik.');
    }

    /**
     * 5. Create Campaign on Meta in PAUSED state.
     * Calls POST /act_{id}/campaigns
     */
    public function createCampaign(AdDraft $draft, ?Setting $setting = null): string
    {
        $setting = $this->requireValidMetaSettings($setting, $draft);
        $adAccountId = $setting->meta_ad_account_id;

        $objective = $draft->destination_type === AdDraft::DESTINATION_WHATSAPP
            ? AdDraft::OBJECTIVE_ENGAGEMENT
            : (in_array($draft->objective, [AdDraft::OBJECTIVE_TRAFFIC, AdDraft::OBJECTIVE_SALES], true)
                ? $draft->objective
                : AdDraft::OBJECTIVE_TRAFFIC);

        $payload = [
            'name' => $draft->title,
            'objective' => $objective,
            'special_ad_categories' => json_encode([]),
            'is_adset_budget_sharing_enabled' => false,
            'status' => 'PAUSED',
            'access_token' => $setting->meta_access_token,
        ];

        $response = Http::timeout(60)
            ->connectTimeout(20)
            ->post(self::BASE_URL . "/{$adAccountId}/campaigns", $payload);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatMetaError($response, 'Create Campaign'));
        }

        $campaignId = (string) ($response->json('id') ?? '');
        if ($campaignId === '') {
            throw new RuntimeException('Meta API tidak mengembalikan campaign_id. Response: ' . $response->body());
        }

        return $campaignId;
    }

    /**
     * 6. Create AdSet on Meta in PAUSED state with coordinate radius targeting, excluded zones, age, gender, and interests.
     * Calls POST /act_{id}/adsets
     */
    public function createAdSet(AdDraft $draft, ?Setting $setting = null): string
    {
        $setting = $this->requireValidMetaSettings($setting, $draft);
        $adAccountId = $setting->meta_ad_account_id;

        if (empty($draft->meta_campaign_id)) {
            throw new RuntimeException('meta_campaign_id belum tersedia saat membuat AdSet.');
        }

        $customLocations = [];
        foreach (($draft->custom_locations ?? []) as $loc) {
            if (isset($loc['latitude'], $loc['longitude'])) {
                $customLocations[] = [
                    'latitude' => round((float) $loc['latitude'], 6),
                    'longitude' => round((float) $loc['longitude'], 6),
                    'radius' => max(1, min(80, (int) ($loc['radius'] ?? 10))),
                    'distance_unit' => 'kilometer',
                ];
            }
        }

        if (empty($customLocations)) {
            $customLocations[] = [
                'latitude' => round((float) ($setting->default_latitude ?: -1.610123), 6),
                'longitude' => round((float) ($setting->default_longitude ?: 103.613120), 6),
                'radius' => 10,
                'distance_unit' => 'kilometer',
            ];
        }

        $targeting = [
            'geo_locations' => [
                'custom_locations' => $customLocations,
                'location_types' => ['home', 'recent'],
            ],
            'age_min' => max(18, min(65, (int) ($draft->age_min ?: 21))),
            'age_max' => max(18, min(65, (int) ($draft->age_max ?: 50))),
        ];

        // Excluded custom locations
        $excludedLocations = [];
        foreach (($draft->excluded_locations ?? []) as $exLoc) {
            if (isset($exLoc['latitude'], $exLoc['longitude'])) {
                $excludedLocations[] = [
                    'latitude' => round((float) $exLoc['latitude'], 6),
                    'longitude' => round((float) $exLoc['longitude'], 6),
                    'radius' => max(1, min(80, (int) ($exLoc['radius'] ?? 5))),
                    'distance_unit' => 'kilometer',
                ];
            }
        }

        if (! empty($excludedLocations)) {
            $targeting['excluded_geo_locations'] = [
                'custom_locations' => $excludedLocations,
            ];
        }

        // Genders
        if (! empty($draft->genders) && is_array($draft->genders)) {
            $validGenders = array_values(array_intersect(array_map('intval', $draft->genders), [1, 2]));
            if (! empty($validGenders)) {
                $targeting['genders'] = $validGenders;
            }
        }

        // Flexible spec for Meta interests
        if (! empty($draft->interests) && is_array($draft->interests)) {
            $interestItems = [];
            foreach ($draft->interests as $interest) {
                if (! empty($interest['id'])) {
                    $interestItems[] = [
                        'id' => (string) $interest['id'],
                        'name' => (string) ($interest['name'] ?? ''),
                    ];
                }
            }
            if (! empty($interestItems)) {
                $targeting['flexible_spec'] = [
                    ['interests' => $interestItems],
                ];
            }
        }

        $payload = [
            'name' => $draft->title . ' - AdSet',
            'campaign_id' => $draft->meta_campaign_id,
            'daily_budget' => max(15000, (int) $draft->daily_budget),
            'billing_event' => 'IMPRESSIONS',
            'targeting' => json_encode($targeting),
            'status' => 'PAUSED',
            'access_token' => $setting->meta_access_token,
        ];

        if ($draft->destination_type === AdDraft::DESTINATION_WHATSAPP) {
            $payload['optimization_goal'] = 'CONVERSATIONS';
            $payload['destination_type'] = 'WHATSAPP';
            $payload['promoted_object'] = json_encode([
                'page_id' => (string) $setting->meta_page_id,
            ]);
        } else {
            $payload['optimization_goal'] = 'LINK_CLICKS';
            $payload['destination_type'] = 'WEBSITE';
            if ($draft->objective === AdDraft::OBJECTIVE_SALES && ! empty($setting->meta_pixel_id)) {
                $payload['optimization_goal'] = 'OFFSITE_CONVERSIONS';
                $payload['promoted_object'] = json_encode([
                    'pixel_id' => (string) $setting->meta_pixel_id,
                    'custom_event_type' => 'PURCHASE',
                ]);
            }
        }

        $response = Http::timeout(60)
            ->connectTimeout(20)
            ->post(self::BASE_URL . "/{$adAccountId}/adsets", $payload);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatMetaError($response, 'Create AdSet'));
        }

        $adSetId = (string) ($response->json('id') ?? '');
        if ($adSetId === '') {
            throw new RuntimeException('Meta API tidak mengembalikan adset_id. Response: ' . $response->body());
        }

        return $adSetId;
    }

    /**
     * 7. Create AdCreative on Meta with video_data and object_story_spec.
     * Calls POST /act_{id}/adcreatives
     */
    public function createAdCreative(AdDraft $draft, ?Setting $setting = null): string
    {
        $setting = $this->requireValidMetaSettings($setting, $draft);
        $adAccountId = $setting->meta_ad_account_id;

        if (empty($draft->meta_video_id)) {
            throw new RuntimeException('meta_video_id belum tersedia saat membuat AdCreative.');
        }

        if (empty($draft->thumbnail_url)) {
            throw new RuntimeException('thumbnail_url wajib tersedia untuk membuat AdCreative video di Meta.');
        }

        $destinationUrl = $draft->resolveDestinationUrl($setting);

        if ($draft->destination_type === AdDraft::DESTINATION_WHATSAPP) {
            $callToAction = [
                'type' => 'WHATSAPP_MESSAGE',
                'value' => [
                    'app_destination' => 'WHATSAPP',
                ],
            ];
        } else {
            $ctaType = in_array($draft->cta_type, ['LEARN_MORE', 'SHOP_NOW', 'WHATSAPP_MESSAGE'], true)
                ? $draft->cta_type
                : 'LEARN_MORE';

            $callToAction = [
                'type' => $ctaType,
                'value' => [
                    'link' => $destinationUrl,
                ],
            ];
        }

        $videoData = [
            'video_id' => (string) $draft->meta_video_id,
            'image_url' => (string) $draft->thumbnail_url,
            'title' => (string) $draft->ai_headline,
            'message' => (string) $draft->ai_primary_text,
            'call_to_action' => $callToAction,
        ];

        if (! empty($draft->ai_description)) {
            $videoData['link_description'] = (string) $draft->ai_description;
        }

        $objectStorySpec = [
            'page_id' => (string) $setting->meta_page_id,
            'video_data' => $videoData,
        ];

        $response = Http::timeout(60)
            ->connectTimeout(20)
            ->post(self::BASE_URL . "/{$adAccountId}/adcreatives", [
                'name' => $draft->title . ' - Creative',
                'object_story_spec' => json_encode($objectStorySpec),
                'access_token' => $setting->meta_access_token,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatMetaError($response, 'Create AdCreative'));
        }

        $creativeId = (string) ($response->json('id') ?? '');
        if ($creativeId === '') {
            throw new RuntimeException('Meta API tidak mengembalikan creative_id. Response: ' . $response->body());
        }

        return $creativeId;
    }

    /**
     * 8. Create Ad on Meta linking adset_id and creative_id in PAUSED state.
     * Calls POST /act_{id}/ads
     */
    public function createAd(AdDraft $draft, ?Setting $setting = null): string
    {
        $setting = $this->requireValidMetaSettings($setting, $draft);
        $adAccountId = $setting->meta_ad_account_id;

        if (empty($draft->meta_adset_id) || empty($draft->meta_creative_id)) {
            throw new RuntimeException('meta_adset_id dan meta_creative_id wajib tersedia sebelum membuat Ad.');
        }

        $response = Http::timeout(60)
            ->connectTimeout(20)
            ->post(self::BASE_URL . "/{$adAccountId}/ads", [
                'name' => $draft->title . ' - Ad',
                'adset_id' => (string) $draft->meta_adset_id,
                'creative' => json_encode([
                    'creative_id' => (string) $draft->meta_creative_id,
                ]),
                'status' => 'PAUSED',
                'access_token' => $setting->meta_access_token,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatMetaError($response, 'Create Ad'));
        }

        $adId = (string) ($response->json('id') ?? '');
        if ($adId === '') {
            throw new RuntimeException('Meta API tidak mengembalikan ad_id. Response: ' . $response->body());
        }

        return $adId;
    }

    /**
     * 9. Update Campaign, AdSet, and Ad status in Meta to 'ACTIVE' or 'PAUSED'.
     */
    public function updateStatus(AdDraft $draft, string $newStatus, ?Setting $setting = null): array
    {
        $setting = $this->requireValidMetaSettings($setting, $draft);
        $normalized = strtoupper(trim($newStatus)) === 'ACTIVE' ? 'ACTIVE' : 'PAUSED';

        $entities = array_filter([
            'Campaign' => $draft->meta_campaign_id,
            'AdSet' => $draft->meta_adset_id,
            'Ad' => $draft->meta_ad_id,
        ]);

        if (empty($entities)) {
            throw new RuntimeException('Iklan ini belum memiliki ID Campaign/AdSet/Ad di Meta Ads.');
        }

        foreach ($entities as $label => $entityId) {
            $response = Http::timeout(45)
                ->connectTimeout(15)
                ->post(self::BASE_URL . "/{$entityId}", [
                    'status' => $normalized,
                    'access_token' => $setting->meta_access_token,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException($this->formatMetaError($response, "Update Status {$label} ({$entityId}) ke {$normalized}"));
            }
        }

        $draft->update([
            'status' => $normalized === 'ACTIVE'
                ? AdDraft::STATUS_ACTIVE_ON_META
                : AdDraft::STATUS_PAUSED_ON_META,
        ]);

        return [
            'ok' => true,
            'status' => $normalized,
            'draft_status' => $draft->status,
        ];
    }

    /**
     * 10. Fetch campaign insights (spend, impressions, clicks, cpc, ctr, actions) from Meta API.
     */
    public function getInsights(string $campaignId, ?Setting $setting = null, string $datePreset = 'today'): array
    {
        $setting = $this->resolveMetaSetting($setting);

        $defaultMetrics = [
            'spend' => 0.0,
            'impressions' => 0,
            'clicks' => 0,
            'cpc' => 0.0,
            'ctr' => 0.0,
            'conversations' => 0,
            'link_clicks' => 0,
            'actions' => [],
        ];

        if (empty($campaignId) || empty($setting->meta_access_token)) {
            return $defaultMetrics;
        }

        try {
            $response = Http::timeout(30)
                ->connectTimeout(15)
                ->get(self::BASE_URL . "/{$campaignId}/insights", [
                    'fields' => 'spend,impressions,clicks,cpc,ctr,actions',
                    'date_preset' => $datePreset,
                    'access_token' => $setting->meta_access_token,
                ]);

            if (! $response->successful()) {
                return $defaultMetrics;
            }

            $row = $response->json('data.0');
            if (! is_array($row)) {
                return $defaultMetrics;
            }

            $actions = $row['actions'] ?? [];
            $conversations = 0;
            $linkClicks = 0;

            if (is_array($actions)) {
                foreach ($actions as $action) {
                    $type = (string) ($action['action_type'] ?? '');
                    $val = (int) ($action['value'] ?? 0);

                    if (in_array($type, [
                        'onsite_conversion.messaging_conversation_started_7d',
                        'onsite_conversion.total_messaging_connection',
                        'messaging_conversation_started_7d',
                    ], true)) {
                        $conversations = max($conversations, $val);
                    }

                    if ($type === 'link_click') {
                        $linkClicks = max($linkClicks, $val);
                    }
                }
            }

            return [
                'spend' => (float) ($row['spend'] ?? 0),
                'impressions' => (int) ($row['impressions'] ?? 0),
                'clicks' => (int) ($row['clicks'] ?? 0),
                'cpc' => (float) ($row['cpc'] ?? 0),
                'ctr' => (float) ($row['ctr'] ?? 0),
                'conversations' => $conversations,
                'link_clicks' => $linkClicks ?: (int) ($row['clicks'] ?? 0),
                'actions' => $actions,
            ];
        } catch (\Throwable $e) {
            return $defaultMetrics;
        }
    }

    protected function resolveMetaSetting(?Setting $setting = null, ?AdDraft $draft = null): Setting
    {
        if ($setting !== null && ! empty($setting->meta_access_token) && ! empty($setting->meta_ad_account_id)) {
            return $setting;
        }

        if ($draft !== null) {
            return $draft->resolveSettingForPlatform(AdDraft::PLATFORM_META, $setting);
        }

        return AdAccount::resolveSettingForPlatform(AdAccount::PROVIDER_META, null, $setting);
    }

    /**
     * Validate that Meta credentials exist in Settings or AdAccounts.
     */
    protected function requireValidMetaSettings(?Setting $setting = null, ?AdDraft $draft = null): Setting
    {
        $setting = $this->resolveMetaSetting($setting, $draft);

        if (empty($setting->meta_access_token)) {
            throw new RuntimeException('Meta Access Token belum diatur di menu Pengaturan Sistem.');
        }

        if (empty($setting->meta_ad_account_id)) {
            throw new RuntimeException('Meta Ad Account ID belum diatur di menu Pengaturan Sistem.');
        }

        if (empty($setting->meta_page_id)) {
            throw new RuntimeException('Meta Page ID belum diatur di menu Pengaturan Sistem.');
        }

        return $setting;
    }

    /**
     * Build detailed human-readable error message from Meta Graph API error response.
     */
    protected function formatMetaError(Response $response, string $context): string
    {
        $error = $response->json('error');

        if (! is_array($error)) {
            return "[{$context}] HTTP {$response->status()}: " . $response->body();
        }

        $parts = ["[{$context}] Meta API Error"];

        if (! empty($error['code'])) {
            $codeStr = 'Code ' . $error['code'];
            if (! empty($error['error_subcode'])) {
                $codeStr .= ' / Subcode ' . $error['error_subcode'];
            }
            $parts[] = "({$codeStr})";
        }

        $mainMsg = $error['message'] ?? 'Unknown Meta Graph API Error';
        $parts[] = ': ' . $mainMsg;

        if (! empty($error['error_user_title']) || ! empty($error['error_user_msg'])) {
            $userDetail = trim(($error['error_user_title'] ?? '') . ' - ' . ($error['error_user_msg'] ?? ''), ' -');
            $parts[] = " | Detail: {$userDetail}";
        }

        if (! empty($error['error_data'])) {
            $parts[] = ' | Data: ' . json_encode($error['error_data'], JSON_UNESCAPED_UNICODE);
        }

        if (! empty($error['fbtrace_id'])) {
            $parts[] = " (fbtrace_id: {$error['fbtrace_id']})";
        }

        return implode('', $parts);
    }
}
