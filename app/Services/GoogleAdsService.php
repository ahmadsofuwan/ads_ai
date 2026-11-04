<?php

namespace App\Services;

use App\Models\AdAccount;
use App\Models\AdDraft;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleAdsService
{
    public const BASE_URL = 'https://googleads.googleapis.com/v18';
    public const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    public const YOUTUBE_UPLOAD_URL = 'https://www.googleapis.com/upload/youtube/v3/videos';

    /**
     * Exchange OAuth2 Refresh Token for a valid Access Token.
     */
    public function getAccessToken(?Setting $setting = null): string
    {
        $setting = $this->requireValidGoogleSettings($setting);

        $response = Http::asForm()->timeout(30)->post(self::OAUTH_TOKEN_URL, [
            'client_id' => (string) $setting->google_client_id,
            'client_secret' => (string) $setting->google_client_secret,
            'refresh_token' => (string) $setting->google_refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            $err = $response->json('error_description') ?? $response->json('error') ?? $response->body();
            throw new RuntimeException("Gagal menukar Google OAuth2 Refresh Token: {$err}");
        }

        $accessToken = (string) ($response->json('access_token') ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('Google OAuth2 tidak mengembalikan access_token.');
        }

        return $accessToken;
    }

    /**
     * 1. Test connection to Google Ads API v18 and fetch Customer Account details.
     */
    public function testConnection(?Setting $setting = null): array
    {
        $setting = $this->resolveGoogleSetting($setting);

        if (empty($setting->google_developer_token)
            || empty($setting->google_client_id)
            || empty($setting->google_client_secret)
            || empty($setting->google_refresh_token)
            || empty($setting->google_customer_id)) {
            return [
                'ok' => false,
                'message' => 'Kredensial Google Ads (Developer Token, OAuth2 Client, Refresh Token, Customer ID) belum lengkap.',
            ];
        }

        try {
            $accessToken = $this->getAccessToken($setting);
            $customerId = $setting->google_customer_id;

            $response = Http::withHeaders($this->buildHeaders($accessToken, $setting))
                ->timeout(30)
                ->post(self::BASE_URL . "/customers/{$customerId}/googleAds:search", [
                    'query' => 'SELECT customer.id, customer.descriptive_name, customer.currency_code, customer.time_zone FROM customer LIMIT 1',
                ]);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'message' => $this->formatGoogleError($response, 'Tes Koneksi Google Ads'),
                ];
            }

            $cust = $response->json('results.0.customer') ?? [];

            return [
                'ok' => true,
                'customer_id' => $cust['id'] ?? $customerId,
                'descriptive_name' => $cust['descriptiveName'] ?? 'Google Ads Account',
                'currency_code' => $cust['currencyCode'] ?? 'IDR',
                'message' => sprintf(
                    'Terhubung ke Google Ads: %s (ID: %s - %s)',
                    $cust['descriptiveName'] ?? 'Customer',
                    $cust['id'] ?? $customerId,
                    $cust['currencyCode'] ?? 'IDR'
                ),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal menghubungi Google Ads API: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 2. Resolve existing YouTube Video ID or upload local MP4 to YouTube Data API v3 as Unlisted.
     */
    public function resolveOrUploadYouTubeVideo(AdDraft $draft, ?Setting $setting = null): string
    {
        if (! empty($draft->youtube_video_id)) {
            $parsed = $this->extractYouTubeId($draft->youtube_video_id);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        $setting = $this->requireValidGoogleSettings($setting, $draft);
        $accessToken = $this->getAccessToken($setting);
        $localFilePath = $draft->getAbsoluteVideoPath();

        if (! file_exists($localFilePath) || ! is_readable($localFilePath)) {
            throw new RuntimeException("File video lokal tidak ditemukan untuk diunggah ke YouTube: {$localFilePath}");
        }

        $metadata = [
            'snippet' => [
                'title' => Str::limit($draft->title, 95, ''),
                'description' => $draft->ai_primary_text,
                'categoryId' => '22',
            ],
            'status' => [
                'privacyStatus' => 'unlisted',
                'selfDeclaredMadeForKids' => false,
            ],
        ];

        // Initiate resumable upload session on YouTube Data API v3
        $initResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-Upload-Content-Length' => (string) filesize($localFilePath),
            'X-Upload-Content-Type' => 'video/mp4',
        ])->timeout(60)->post(self::YOUTUBE_UPLOAD_URL . '?uploadType=resumable&part=snippet,status', $metadata);

        if (! $initResponse->successful()) {
            throw new RuntimeException($this->formatGoogleError($initResponse, 'Inisialisasi Upload YouTube API v3'));
        }

        $uploadUrl = $initResponse->header('Location');
        if (empty($uploadUrl)) {
            // Direct response fallback (e.g., in mocked tests or single-step responses)
            $directId = (string) ($initResponse->json('id') ?? '');
            if ($directId !== '') {
                return $directId;
            }
            throw new RuntimeException('YouTube API tidak mengembalikan header Location untuk resumable upload.');
        }

        $videoBytes = file_get_contents($localFilePath);
        if ($videoBytes === false) {
            throw new RuntimeException("Gagal membaca isi file video: {$localFilePath}");
        }

        $uploadResp = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'video/mp4',
        ])
            ->timeout(600)
            ->withBody($videoBytes, 'video/mp4')
            ->put($uploadUrl);

        if (! $uploadResp->successful()) {
            throw new RuntimeException($this->formatGoogleError($uploadResp, 'Upload Binary Video ke YouTube API v3'));
        }

        $youtubeId = (string) ($uploadResp->json('id') ?? '');
        if ($youtubeId === '') {
            throw new RuntimeException('YouTube Data API v3 tidak mengembalikan video id setelah unggah selesai.');
        }

        return $youtubeId;
    }

    /**
     * 3. Create YouTubeVideoAsset in Google Ads API v18.
     */
    public function createYouTubeVideoAsset(string $youtubeVideoId, string $title, ?Setting $setting = null): string
    {
        $setting = $this->requireValidGoogleSettings($setting);
        $accessToken = $this->getAccessToken($setting);
        $customerId = $setting->google_customer_id;

        $response = Http::withHeaders($this->buildHeaders($accessToken, $setting))
            ->timeout(45)
            ->post(self::BASE_URL . "/customers/{$customerId}/assets:mutate", [
                'operations' => [
                    [
                        'create' => [
                            'name' => Str::limit($title . ' - Video Asset ' . time(), 120, ''),
                            'type' => 'YOUTUBE_VIDEO',
                            'youtubeVideoAsset' => [
                                'youtubeVideoId' => $youtubeVideoId,
                            ],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatGoogleError($response, 'Create YouTube Video Asset'));
        }

        $resourceName = (string) ($response->json('results.0.resourceName') ?? '');
        if ($resourceName === '') {
            throw new RuntimeException('Google Ads API tidak mengembalikan resourceName untuk YouTubeVideoAsset.');
        }

        return $resourceName;
    }

    /**
     * 4. Create CampaignBudget in Google Ads API v18.
     */
    public function createCampaignBudget(AdDraft $draft, ?Setting $setting = null): string
    {
        $setting = $this->requireValidGoogleSettings($setting, $draft);
        $accessToken = $this->getAccessToken($setting);
        $customerId = $setting->google_customer_id;

        $amountMicros = max(15000, (int) $draft->daily_budget) * 1000000;

        $response = Http::withHeaders($this->buildHeaders($accessToken, $setting))
            ->timeout(45)
            ->post(self::BASE_URL . "/customers/{$customerId}/campaignBudgets:mutate", [
                'operations' => [
                    [
                        'create' => [
                            'name' => Str::limit($draft->title . ' Budget #' . $draft->id . '_' . time(), 150, ''),
                            'amountMicros' => (string) $amountMicros,
                            'deliveryMethod' => 'STANDARD',
                            'explicitlyShared' => false,
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatGoogleError($response, 'Create CampaignBudget Google Ads'));
        }

        $resourceName = (string) ($response->json('results.0.resourceName') ?? '');
        if ($resourceName === '') {
            throw new RuntimeException('Google Ads API tidak mengembalikan resourceName untuk CampaignBudget.');
        }

        return $resourceName;
    }

    /**
     * 5. Create Campaign in PAUSED state on Google Ads API v18.
     */
    public function createCampaign(AdDraft $draft, string $budgetResourceName, ?Setting $setting = null): string
    {
        $setting = $this->requireValidGoogleSettings($setting, $draft);
        $accessToken = $this->getAccessToken($setting);
        $customerId = $setting->google_customer_id;

        $response = Http::withHeaders($this->buildHeaders($accessToken, $setting))
            ->timeout(45)
            ->post(self::BASE_URL . "/customers/{$customerId}/campaigns:mutate", [
                'operations' => [
                    [
                        'create' => [
                            'name' => Str::limit($draft->title . ' (Google Ads #' . $draft->id . ')', 180, ''),
                            'advertisingChannelType' => 'DEMAND_GEN',
                            'status' => 'PAUSED',
                            'campaignBudget' => $budgetResourceName,
                            'maximizeConversions' => new \stdClass(),
                            'containsEuPoliticalAdvertising' => 'DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING',
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatGoogleError($response, 'Create Campaign Google Ads'));
        }

        $resourceName = (string) ($response->json('results.0.resourceName') ?? '');
        if ($resourceName === '') {
            throw new RuntimeException('Google Ads API tidak mengembalikan resourceName untuk Campaign.');
        }

        return $this->extractEntityIdFromResource($resourceName);
    }

    /**
     * 6. Attach Coordinate + Radius (Proximity) Geo-Targeting to Google Ads Campaign.
     */
    public function attachProximityGeoTargeting(AdDraft $draft, string $campaignId, ?Setting $setting = null): void
    {
        $setting = $this->requireValidGoogleSettings($setting, $draft);
        $accessToken = $this->getAccessToken($setting);
        $customerId = $setting->google_customer_id;
        $campaignResource = "customers/{$customerId}/campaigns/{$campaignId}";

        $operations = [];

        foreach (($draft->custom_locations ?? []) as $loc) {
            if (isset($loc['latitude'], $loc['longitude'])) {
                $latMicro = (int) round(((float) $loc['latitude']) * 1000000);
                $lngMicro = (int) round(((float) $loc['longitude']) * 1000000);
                $radiusKm = max(1, min(80, (int) ($loc['radius'] ?? 10)));

                $operations[] = [
                    'create' => [
                        'campaign' => $campaignResource,
                        'negative' => false,
                        'proximity' => [
                            'geoPoint' => [
                                'latitudeInMicroDegrees' => $latMicro,
                                'longitudeInMicroDegrees' => $lngMicro,
                            ],
                            'radius' => (float) $radiusKm,
                            'radiusUnits' => 'KILOMETERS',
                        ],
                    ],
                ];
            }
        }

        if (empty($operations)) {
            return;
        }

        $response = Http::withHeaders($this->buildHeaders($accessToken, $setting))
            ->timeout(45)
            ->post(self::BASE_URL . "/customers/{$customerId}/campaignCriteria:mutate", [
                'operations' => $operations,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatGoogleError($response, 'Attach Proximity Geo-Targeting Google Ads'));
        }
    }

    /**
     * 7. Create AdGroup in PAUSED state on Google Ads API v18.
     */
    public function createAdGroup(AdDraft $draft, string $campaignId, ?Setting $setting = null): string
    {
        $setting = $this->requireValidGoogleSettings($setting, $draft);
        $accessToken = $this->getAccessToken($setting);
        $customerId = $setting->google_customer_id;
        $campaignResource = "customers/{$customerId}/campaigns/{$campaignId}";

        $response = Http::withHeaders($this->buildHeaders($accessToken, $setting))
            ->timeout(45)
            ->post(self::BASE_URL . "/customers/{$customerId}/adGroups:mutate", [
                'operations' => [
                    [
                        'create' => [
                            'name' => Str::limit($draft->title . ' - AdGroup', 180, ''),
                            'campaign' => $campaignResource,
                            'status' => 'PAUSED',
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatGoogleError($response, 'Create AdGroup Google Ads'));
        }

        $resourceName = (string) ($response->json('results.0.resourceName') ?? '');
        if ($resourceName === '') {
            throw new RuntimeException('Google Ads API tidak mengembalikan resourceName untuk AdGroup.');
        }

        return $this->extractEntityIdFromResource($resourceName);
    }

    /**
     * 8. Create VideoResponsiveAd in PAUSED state on Google Ads API v18.
     */
    public function createVideoResponsiveAd(
        AdDraft $draft,
        string $adGroupId,
        string $videoAssetResourceName,
        ?Setting $setting = null
    ): string {
        $setting = $this->requireValidGoogleSettings($setting, $draft);
        $accessToken = $this->getAccessToken($setting);
        $customerId = $setting->google_customer_id;
        $adGroupResource = "customers/{$customerId}/adGroups/{$adGroupId}";

        $destinationUrl = $draft->resolveDestinationUrl($setting);
        $shortHeadline = Str::limit(trim((string) ($draft->google_headline ?: $draft->ai_headline)), 30, '');
        $longHeadline = Str::limit(trim((string) ($draft->google_long_headline ?: $draft->ai_headline)), 90, '');
        $description = Str::limit(trim((string) ($draft->google_description ?: ($draft->ai_description ?: $draft->ai_primary_text))), 90, '');

        $response = Http::withHeaders($this->buildHeaders($accessToken, $setting))
            ->timeout(45)
            ->post(self::BASE_URL . "/customers/{$customerId}/adGroupAds:mutate", [
                'operations' => [
                    [
                        'create' => [
                            'adGroup' => $adGroupResource,
                            'status' => 'PAUSED',
                            'ad' => [
                                'name' => Str::limit($draft->title . ' - Video Ad', 180, ''),
                                'finalUrls' => [$destinationUrl],
                                'videoResponsiveAd' => [
                                    'videos' => [
                                        ['asset' => $videoAssetResourceName],
                                    ],
                                    'headlines' => [
                                        ['text' => $shortHeadline],
                                    ],
                                    'longHeadlines' => [
                                        ['text' => $longHeadline],
                                    ],
                                    'descriptions' => [
                                        ['text' => $description],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatGoogleError($response, 'Create VideoResponsiveAd Google Ads'));
        }

        $resourceName = (string) ($response->json('results.0.resourceName') ?? '');
        if ($resourceName === '') {
            throw new RuntimeException('Google Ads API tidak mengembalikan resourceName untuk AdGroupAd.');
        }

        return $this->extractEntityIdFromResource($resourceName);
    }

    /**
     * 9. Update Campaign & AdGroup status on Google Ads ('ACTIVE' => 'ENABLED', 'PAUSED' => 'PAUSED').
     */
    public function updateStatus(AdDraft $draft, string $newStatus, ?Setting $setting = null): array
    {
        $setting = $this->requireValidGoogleSettings($setting, $draft);
        $accessToken = $this->getAccessToken($setting);
        $customerId = $setting->google_customer_id;

        if (empty($draft->google_campaign_id)) {
            throw new RuntimeException('Kampanye ini belum memiliki google_campaign_id di Google Ads.');
        }

        $isEnable = in_array(strtoupper(trim($newStatus)), ['ACTIVE', 'ENABLED', 'ENABLE'], true);
        $googleStatus = $isEnable ? 'ENABLED' : 'PAUSED';

        $campaignResource = "customers/{$customerId}/campaigns/{$draft->google_campaign_id}";

        $response = Http::withHeaders($this->buildHeaders($accessToken, $setting))
            ->timeout(45)
            ->post(self::BASE_URL . "/customers/{$customerId}/campaigns:mutate", [
                'operations' => [
                    [
                        'updateMask' => 'status',
                        'update' => [
                            'resourceName' => $campaignResource,
                            'status' => $googleStatus,
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatGoogleError($response, 'Update Status Campaign Google Ads'));
        }

        if (! empty($draft->google_adgroup_id)) {
            $adGroupResource = "customers/{$customerId}/adGroups/{$draft->google_adgroup_id}";
            Http::withHeaders($this->buildHeaders($accessToken, $setting))
                ->timeout(30)
                ->post(self::BASE_URL . "/customers/{$customerId}/adGroups:mutate", [
                    'operations' => [
                        [
                            'updateMask' => 'status',
                            'update' => [
                                'resourceName' => $adGroupResource,
                                'status' => $googleStatus,
                            ],
                        ],
                    ],
                ]);
        }

        $draft->update([
            'google_status' => $isEnable
                ? AdDraft::GOOGLE_STATUS_ACTIVE
                : AdDraft::GOOGLE_STATUS_PAUSED,
        ]);

        return [
            'ok' => true,
            'status' => $googleStatus,
            'google_status' => $draft->google_status,
        ];
    }

    /**
     * 10. Fetch campaign performance metrics from Google Ads API v18 via GAQL.
     */
    public function getInsights(string $campaignId, ?Setting $setting = null, string $datePreset = 'today'): array
    {
        $setting = $this->resolveGoogleSetting($setting);

        $defaults = [
            'spend' => 0.0,
            'impressions' => 0,
            'clicks' => 0,
            'cpc' => 0.0,
            'ctr' => 0.0,
            'conversations' => 0,
        ];

        if (empty($campaignId) || empty($setting->google_developer_token) || empty($setting->google_customer_id)) {
            return $defaults;
        }

        $dateCondition = match ($datePreset) {
            'yesterday' => ' segments.date DURING YESTERDAY AND ',
            'last_7d' => ' segments.date DURING LAST_7_DAYS AND ',
            'maximum' => ' ',
            default => ' segments.date DURING TODAY AND ',
        };

        try {
            $accessToken = $this->getAccessToken($setting);
            $customerId = $setting->google_customer_id;

            $gaql = "SELECT campaign.id, metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.average_cpc, metrics.ctr, metrics.conversions "
                . "FROM campaign WHERE{$dateCondition}campaign.id = {$campaignId} LIMIT 1";

            $response = Http::withHeaders($this->buildHeaders($accessToken, $setting))
                ->timeout(30)
                ->post(self::BASE_URL . "/customers/{$customerId}/googleAds:search", [
                    'query' => $gaql,
                ]);

            if (! $response->successful()) {
                return $defaults;
            }

            $metrics = $response->json('results.0.metrics');
            if (! is_array($metrics)) {
                return $defaults;
            }

            $costMicros = (float) ($metrics['costMicros'] ?? 0);
            $avgCpcMicros = (float) ($metrics['averageCpc'] ?? 0);

            return [
                'spend' => round($costMicros / 1000000, 2),
                'impressions' => (int) ($metrics['impressions'] ?? 0),
                'clicks' => (int) ($metrics['clicks'] ?? 0),
                'cpc' => round($avgCpcMicros / 1000000, 2),
                'ctr' => round(((float) ($metrics['ctr'] ?? 0)) * 100, 2),
                'conversations' => (int) round((float) ($metrics['conversions'] ?? 0)),
            ];
        } catch (\Throwable $e) {
            return $defaults;
        }
    }

    /**
     * Extract 11-character YouTube Video ID from a URL or plain ID string.
     */
    public function extractYouTubeId(string $input): ?string
    {
        $clean = trim($input);
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $clean)) {
            return $clean;
        }

        if (preg_match('#(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([a-zA-Z0-9_-]{11})#i', $clean, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function extractEntityIdFromResource(string $resourceName): string
    {
        $parts = explode('/', $resourceName);

        return (string) end($parts);
    }

    protected function buildHeaders(string $accessToken, Setting $setting): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'developer-token' => (string) $setting->google_developer_token,
            'Content-Type' => 'application/json',
        ];

        if (! empty($setting->google_login_customer_id)) {
            $headers['login-customer-id'] = (string) $setting->google_login_customer_id;
        }

        return $headers;
    }

    protected function resolveGoogleSetting(?Setting $setting = null, ?AdDraft $draft = null): Setting
    {
        if ($setting !== null
            && ! empty($setting->google_developer_token)
            && ! empty($setting->google_client_id)
            && ! empty($setting->google_client_secret)
            && ! empty($setting->google_refresh_token)
            && ! empty($setting->google_customer_id)) {
            return $setting;
        }

        if ($draft !== null) {
            return $draft->resolveSettingForPlatform(AdDraft::PLATFORM_GOOGLE, $setting);
        }

        return AdAccount::resolveSettingForPlatform(AdAccount::PROVIDER_GOOGLE, null, $setting);
    }

    protected function requireValidGoogleSettings(?Setting $setting = null, ?AdDraft $draft = null): Setting
    {
        $setting = $this->resolveGoogleSetting($setting, $draft);

        if (empty($setting->google_developer_token)
            || empty($setting->google_client_id)
            || empty($setting->google_client_secret)
            || empty($setting->google_refresh_token)
            || empty($setting->google_customer_id)) {
            throw new RuntimeException('Kredensial Google Ads API v18 (Developer Token, OAuth2 Client ID/Secret, Refresh Token, Customer ID) belum lengkap di Pengaturan Sistem.');
        }

        return $setting;
    }

    protected function formatGoogleError(Response $response, string $context): string
    {
        $err = $response->json('error');
        if (! is_array($err)) {
            return "[{$context}] HTTP {$response->status()}: " . $response->body();
        }

        $msg = $err['message'] ?? 'Unknown Google API Error';
        $status = $err['status'] ?? $response->status();
        $details = ! empty($err['details']) ? ' | Details: ' . json_encode($err['details'], JSON_UNESCAPED_UNICODE) : '';

        return "[{$context}] Google API Error ({$status}): {$msg}{$details}";
    }
}
