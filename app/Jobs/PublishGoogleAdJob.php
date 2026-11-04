<?php

namespace App\Jobs;

use App\Models\AdDraft;
use App\Models\Setting;
use App\Services\GoogleAdsService;
use App\Services\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishGoogleAdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public int $adDraftId,
        public ?string $telegramChatId = null
    ) {}

    public function handle(
        GoogleAdsService $googleService,
        TelegramBotService $telegramService
    ): void {
        $draft = AdDraft::find($this->adDraftId);

        if (! $draft) {
            return;
        }

        $setting = $draft->resolveSettingForPlatform(AdDraft::PLATFORM_GOOGLE);
        $chatId = $this->telegramChatId ?: $draft->telegram_chat_id;

        try {
            $draft->update([
                'google_status' => AdDraft::GOOGLE_STATUS_UPLOADING_YT,
                'google_error_log' => null,
            ]);

            // 1. Resolve or Upload Video to YouTube Data API v3 (Unlisted)
            $youtubeId = $googleService->resolveOrUploadYouTubeVideo($draft, $setting);
            if ($draft->youtube_video_id !== $youtubeId) {
                $draft->update(['youtube_video_id' => $youtubeId]);
                $draft->refresh();
            }

            $draft->update([
                'google_status' => AdDraft::GOOGLE_STATUS_PUBLISHING,
            ]);

            // 2. Create YouTube Video Asset in Google Ads API v18
            if (empty($draft->google_video_asset_resource)) {
                $assetResource = $googleService->createYouTubeVideoAsset($draft->youtube_video_id, $draft->title, $setting);
                $draft->update(['google_video_asset_resource' => $assetResource]);
                $draft->refresh();
            }

            // 3. Create CampaignBudget in Google Ads
            if (empty($draft->google_budget_resource)) {
                $budgetResource = $googleService->createCampaignBudget($draft, $setting);
                $draft->update(['google_budget_resource' => $budgetResource]);
                $draft->refresh();
            }

            // 4. Create Campaign (PAUSED) & Attach Proximity Coordinate + Radius Targeting
            if (empty($draft->google_campaign_id)) {
                $campaignId = $googleService->createCampaign($draft, $draft->google_budget_resource, $setting);
                $draft->update(['google_campaign_id' => $campaignId]);
                $draft->refresh();

                $googleService->attachProximityGeoTargeting($draft, $campaignId, $setting);
            }

            // 5. Create AdGroup (PAUSED)
            if (empty($draft->google_adgroup_id)) {
                $adGroupId = $googleService->createAdGroup($draft, $draft->google_campaign_id, $setting);
                $draft->update(['google_adgroup_id' => $adGroupId]);
                $draft->refresh();
            }

            // 6. Create VideoResponsiveAd (PAUSED)
            if (empty($draft->google_ad_id)) {
                $adId = $googleService->createVideoResponsiveAd(
                    $draft,
                    $draft->google_adgroup_id,
                    $draft->google_video_asset_resource,
                    $setting
                );
                $draft->update(['google_ad_id' => $adId]);
                $draft->refresh();
            }

            // 7. Mark as PAUSED_ON_GOOGLE
            $draft->update([
                'google_status' => AdDraft::GOOGLE_STATUS_PAUSED,
                'google_error_log' => null,
            ]);

            if (! empty($chatId) && $setting->hasTelegramCredentials()) {
                $telegramService->notifyGooglePublishSuccess($draft, (string) $chatId, $setting);
            }
        } catch (\Throwable $e) {
            $errorDetail = sprintf(
                "[%s] Gagal menerbitkan iklan ke Google Ads API v18:\n%s",
                now()->format('Y-m-d H:i:s'),
                $e->getMessage()
            );

            Log::error('PublishGoogleAdJob Failed', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);

            $draft->update([
                'google_status' => AdDraft::GOOGLE_STATUS_FAILED,
                'google_error_log' => $errorDetail,
            ]);

            if (! empty($chatId) && $setting->hasTelegramCredentials()) {
                $telegramService->notifyGooglePublishFailed($draft, (string) $chatId, $e->getMessage(), $setting);
            }
        }
    }
}
