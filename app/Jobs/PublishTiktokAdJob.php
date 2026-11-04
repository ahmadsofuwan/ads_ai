<?php

namespace App\Jobs;

use App\Models\AdDraft;
use App\Models\Setting;
use App\Services\TelegramBotService;
use App\Services\TikTokMarketingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishTiktokAdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public int $adDraftId,
        public ?string $telegramChatId = null
    ) {}

    public function handle(
        TikTokMarketingService $tiktokService,
        TelegramBotService $telegramService
    ): void {
        $draft = AdDraft::find($this->adDraftId);

        if (! $draft) {
            return;
        }

        $setting = $draft->resolveSettingForPlatform(AdDraft::PLATFORM_TIKTOK);
        $chatId = $this->telegramChatId ?: $draft->telegram_chat_id;

        try {
            $draft->update([
                'tiktok_status' => AdDraft::TIKTOK_STATUS_UPLOADING,
                'tiktok_error_log' => null,
            ]);

            $coverUrl = null;

            // 1. Upload Video to TikTok Ads Library
            if (empty($draft->tiktok_video_id)) {
                $localPath = $draft->getAbsoluteVideoPath();
                $uploaded = $tiktokService->uploadVideo($localPath, $setting);
                $coverUrl = $uploaded['cover_url'] ?? null;
                $draft->update(['tiktok_video_id' => $uploaded['video_id']]);
                $draft->refresh();
            }

            // 2. Resolve Cover Image ID for TikTok Video Ad
            if (empty($draft->tiktok_image_id)) {
                $imageId = $tiktokService->resolveCoverImageId($draft->tiktok_video_id, $coverUrl, $setting);
                $draft->update(['tiktok_image_id' => $imageId]);
                $draft->refresh();
            }

            $draft->update([
                'tiktok_status' => AdDraft::TIKTOK_STATUS_PUBLISHING,
            ]);

            // 3. Create Campaign (DISABLE / Paused)
            if (empty($draft->tiktok_campaign_id)) {
                $campaignId = $tiktokService->createCampaign($draft, $setting);
                $draft->update(['tiktok_campaign_id' => $campaignId]);
                $draft->refresh();
            }

            // 4. Create AdGroup (DISABLE / Paused)
            if (empty($draft->tiktok_adgroup_id)) {
                $adgroupId = $tiktokService->createAdGroup($draft, $setting);
                $draft->update(['tiktok_adgroup_id' => $adgroupId]);
                $draft->refresh();
            }

            // 5. Create Ad (DISABLE / Paused)
            if (empty($draft->tiktok_ad_id)) {
                $adId = $tiktokService->createAd($draft, $setting);
                $draft->update(['tiktok_ad_id' => $adId]);
                $draft->refresh();
            }

            // 6. Mark as PAUSED_ON_TIKTOK
            $draft->update([
                'tiktok_status' => AdDraft::TIKTOK_STATUS_PAUSED,
                'tiktok_error_log' => null,
            ]);

            if (! empty($chatId) && $setting->hasTelegramCredentials()) {
                $telegramService->notifyTiktokPublishSuccess($draft, (string) $chatId, $setting);
            }
        } catch (\Throwable $e) {
            $errorDetail = sprintf(
                "[%s] Gagal menerbitkan iklan ke TikTok Marketing API v1.3:\n%s",
                now()->format('Y-m-d H:i:s'),
                $e->getMessage()
            );

            Log::error('PublishTiktokAdJob Failed', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);

            $draft->update([
                'tiktok_status' => AdDraft::TIKTOK_STATUS_FAILED,
                'tiktok_error_log' => $errorDetail,
            ]);

            if (! empty($chatId) && $setting->hasTelegramCredentials()) {
                $telegramService->notifyTiktokPublishFailed($draft, (string) $chatId, $e->getMessage(), $setting);
            }
        }
    }
}
