<?php

namespace App\Jobs;

use App\Models\AdDraft;
use App\Models\Setting;
use App\Services\MetaMarketingService;
use App\Services\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishMetaAdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public int $adDraftId,
        public ?string $telegramChatId = null
    ) {}

    public function handle(
        MetaMarketingService $metaService,
        TelegramBotService $telegramService
    ): void {
        $draft = AdDraft::find($this->adDraftId);

        if (! $draft) {
            return;
        }

        $setting = $draft->resolveSettingForPlatform(AdDraft::PLATFORM_META);
        $chatId = $this->telegramChatId ?: $draft->telegram_chat_id;

        try {
            $draft->update([
                'status' => AdDraft::STATUS_UPLOADING_VIDEO,
                'error_log' => null,
            ]);

            // 1. Upload Video to Meta Ads if not yet uploaded
            if (empty($draft->meta_video_id)) {
                $localPath = $draft->getAbsoluteVideoPath();
                $videoId = $metaService->uploadVideo($localPath, $setting);
                $draft->update(['meta_video_id' => $videoId]);
                $draft->refresh();
            }

            // 2. Poll Video Status until Ready & Fetch Primary Thumbnail URL
            if (empty($draft->thumbnail_url)) {
                $thumbnailUrl = $metaService->waitForVideoAndGetThumbnail($draft->meta_video_id, $setting);
                $draft->update(['thumbnail_url' => $thumbnailUrl]);
                $draft->refresh();
            }

            // 3. Switch state to PUBLISHING_TO_META
            $draft->update([
                'status' => AdDraft::STATUS_PUBLISHING_TO_META,
            ]);

            // 4. Create Campaign (PAUSED)
            if (empty($draft->meta_campaign_id)) {
                $campaignId = $metaService->createCampaign($draft, $setting);
                $draft->update(['meta_campaign_id' => $campaignId]);
                $draft->refresh();
            }

            // 5. Create AdSet (PAUSED)
            if (empty($draft->meta_adset_id)) {
                $adSetId = $metaService->createAdSet($draft, $setting);
                $draft->update(['meta_adset_id' => $adSetId]);
                $draft->refresh();
            }

            // 6. Create AdCreative
            if (empty($draft->meta_creative_id)) {
                $creativeId = $metaService->createAdCreative($draft, $setting);
                $draft->update(['meta_creative_id' => $creativeId]);
                $draft->refresh();
            }

            // 7. Create Ad (PAUSED)
            if (empty($draft->meta_ad_id)) {
                $adId = $metaService->createAd($draft, $setting);
                $draft->update(['meta_ad_id' => $adId]);
                $draft->refresh();
            }

            // 8. Mark as PAUSED_ON_META
            $draft->update([
                'status' => AdDraft::STATUS_PAUSED_ON_META,
                'error_log' => null,
            ]);

            // 9. Notify Telegram if initiated from or linked to Telegram
            if (! empty($chatId) && $setting->hasTelegramCredentials()) {
                $telegramService->notifyPublishSuccess($draft, (string) $chatId, $setting);
            }
        } catch (\Throwable $e) {
            $errorDetail = sprintf(
                "[%s] Gagal menerbitkan iklan ke Meta API:\n%s",
                now()->format('Y-m-d H:i:s'),
                $e->getMessage()
            );

            Log::error('PublishMetaAdJob Failed', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);

            $draft->update([
                'status' => AdDraft::STATUS_FAILED,
                'error_log' => $errorDetail,
            ]);

            if (! empty($chatId) && $setting->hasTelegramCredentials()) {
                $telegramService->notifyPublishFailed($draft, (string) $chatId, $e->getMessage(), $setting);
            }
        }
    }
}
