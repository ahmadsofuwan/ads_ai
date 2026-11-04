<?php

namespace App\Services;

use App\Jobs\PublishGoogleAdJob;
use App\Jobs\PublishMetaAdJob;
use App\Jobs\PublishTiktokAdJob;
use App\Models\AdDraft;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TelegramBotService
{
    public function __construct(
        protected OpenRouterService $openRouterService,
        protected MetaMarketingService $metaService,
        protected TikTokMarketingService $tiktokService,
        protected GoogleAdsService $googleService
    ) {}

    /**
     * Test Telegram Bot token and return bot username/details.
     */
    public function testConnection(?Setting $setting = null): array
    {
        $setting = $setting ?? Setting::current();
        $token = trim((string) $setting->telegram_bot_token);

        if ($token === '') {
            return [
                'ok' => false,
                'message' => 'Telegram Bot Token belum diisi pada Pengaturan Sistem.',
            ];
        }

        try {
            $response = Http::timeout(20)->get("https://api.telegram.org/bot{$token}/getMe");
            if (! $response->successful() || ! $response->json('ok')) {
                return [
                    'ok' => false,
                    'message' => 'Token Telegram tidak valid: ' . ($response->json('description') ?? $response->body()),
                ];
            }

            $bot = $response->json('result');

            return [
                'ok' => true,
                'username' => $bot['username'] ?? '',
                'first_name' => $bot['first_name'] ?? 'Bot',
                'message' => sprintf('Terhubung ke Bot @%s (%s)', $bot['username'] ?? 'bot', $bot['first_name'] ?? ''),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal menghubungi Telegram API: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Set Telegram Webhook URL automatically.
     */
    public function setWebhook(string $baseOrFullUrl, ?Setting $setting = null): array
    {
        $setting = $setting ?? Setting::current();
        $token = trim((string) $setting->telegram_bot_token);

        if ($token === '') {
            throw new RuntimeException('Telegram Bot Token belum diisi. Simpan token terlebih dahulu.');
        }

        $cleanUrl = rtrim(trim($baseOrFullUrl), '/');
        if (! str_ends_with($cleanUrl, '/api/telegram/webhook')) {
            $cleanUrl .= '/api/telegram/webhook';
        }

        $response = Http::timeout(30)->post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $cleanUrl,
            'allowed_updates' => ['message', 'callback_query'],
            'drop_pending_updates' => false,
        ]);

        if (! $response->successful() || ! $response->json('ok')) {
            throw new RuntimeException('Gagal memasang Webhook Telegram: ' . ($response->json('description') ?? $response->body()));
        }

        return [
            'ok' => true,
            'webhook_url' => $cleanUrl,
            'description' => $response->json('description') ?? 'Webhook was set',
        ];
    }

    /**
     * Handle incoming Telegram webhook update payload.
     */
    public function handleWebhook(array $update): void
    {
        $setting = Setting::current();

        if (! $setting->hasTelegramCredentials()) {
            return;
        }

        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query'], $setting);

            return;
        }

        $message = $update['message'] ?? $update['edited_message'] ?? null;
        if (! is_array($message)) {
            return;
        }

        $chatId = (string) ($message['chat']['id'] ?? '');
        if ($chatId === '') {
            return;
        }

        // 1. Security Check: allowed_telegram_chat_ids
        if (! $setting->isChatAllowed($chatId)) {
            $this->sendMessage(
                $chatId,
                "⛔ *Akses Ditolak*\nID Telegram Anda (`{$chatId}`) belum terdaftar di daftar `allowed_telegram_chat_ids` pada sistem Omni-Channel AI Ads Publisher.",
                null,
                $setting
            );

            return;
        }

        // 2. Handle Share Location (message.location)
        if (isset($message['location']) && is_array($message['location'])) {
            $lat = round((float) ($message['location']['latitude'] ?? 0), 6);
            $lng = round((float) ($message['location']['longitude'] ?? 0), 6);

            Cache::put($this->locationCacheKey($chatId), [
                'latitude' => $lat,
                'longitude' => $lng,
            ], now()->addHours(24));

            $replyText = "📍 Titik koordinat ({$lat}, {$lng}) tersimpan! Sekarang kirimkan video iklannya beserta caption perintah (contoh: radius 10km, umur 24-45 tahun, budget 50rb/hari arahkan ke WA).";
            $this->sendMessage($chatId, $replyText, null, $setting);

            return;
        }

        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));

        // 3. Handle Bot Commands (/start, /help, /status)
        if (str_starts_with($text, '/start') || str_starts_with($text, '/help')) {
            $this->handleStartCommand($chatId, $setting);

            return;
        }

        if (str_starts_with($text, '/status')) {
            $this->handleStatusCommand($chatId, $setting);

            return;
        }

        // 4. Handle Video Upload + Caption Command
        $videoFileId = $this->extractVideoFileId($message);
        if ($videoFileId !== null) {
            if ($text === '') {
                Cache::put($this->videoCacheKey($chatId), $videoFileId, now()->addHours(2));
                $this->sendMessage(
                    $chatId,
                    "🎬 *Video diterima!* Silakan balas dengan teks perintah iklan Anda (contoh: _Promo pasang WiFi murah radius 10km, umur 24-45 tahun, budget 50rb/hari arahkan ke WA_).",
                    null,
                    $setting
                );

                return;
            }

            $this->processVideoAndCommand($chatId, $videoFileId, $text, $setting);

            return;
        }

        // 5. If user sent text after previously sending a video without caption
        $cachedVideoFileId = Cache::pull($this->videoCacheKey($chatId));
        if (! empty($cachedVideoFileId) && $text !== '') {
            $this->processVideoAndCommand($chatId, (string) $cachedVideoFileId, $text, $setting);

            return;
        }

        // 6. Check if user sent plain text containing coordinates
        $textCoords = $this->openRouterService->extractCoordinatesFromText($text);
        if ($textCoords !== null && mb_strlen($text) < 45) {
            Cache::put($this->locationCacheKey($chatId), $textCoords, now()->addHours(24));
            $this->sendMessage(
                $chatId,
                "📍 Titik koordinat ({$textCoords['latitude']}, {$textCoords['longitude']}) tersimpan! Sekarang kirimkan video iklannya beserta caption perintah (contoh: radius 10km, umur 24-45 tahun, budget 50rb/hari arahkan ke WA).",
                null,
                $setting
            );

            return;
        }

        $this->sendMessage(
            $chatId,
            "🤖 *Omni-Channel AI Ads Publisher Bot (Meta + TikTok + Google Ads)*\n\n"
            . "Cara membuat iklan baru:\n"
            . "1️⃣ Kirim *Share Location* (opsional) atau tulis koordinat di teks.\n"
            . "2️⃣ Kirim *Video Iklan* beserta *Caption Perintah* (contoh: _radius 10km, umur 24-45 tahun, budget 50rb/hari arahkan ke WA_).\n"
            . "3️⃣ Ketik `/status` untuk memantau biaya (spend) & mengaktifkan/mematikan iklan di Meta, TikTok, dan Google Ads.",
            null,
            $setting
        );
    }

    /**
     * Download video from Telegram, generate Omni-Channel AI copywriting & targeting, save AdDraft, and reply with Inline Keyboard.
     */
    protected function processVideoAndCommand(
        string $chatId,
        string $fileId,
        string $captionPrompt,
        Setting $setting
    ): void {
        try {
            $this->sendMessage(
                $chatId,
                "⏳ *Sedang memproses...*\nMengunduh video dari Telegram & menyusun copywriting AI untuk Meta Ads, TikTok Ads, dan Google Ads...",
                null,
                $setting
            );

            $localVideoPath = $this->downloadTelegramVideo($fileId, $setting);

            $coords = $this->openRouterService->extractCoordinatesFromText($captionPrompt);
            $locationSource = 'Teks Perintah';

            if (! $coords) {
                $cachedLoc = Cache::get($this->locationCacheKey($chatId));
                if (is_array($cachedLoc) && isset($cachedLoc['latitude'], $cachedLoc['longitude'])) {
                    $coords = $cachedLoc;
                    $locationSource = 'Share Location Telegram';
                }
            }

            if (! $coords) {
                $coords = [
                    'latitude' => round((float) ($setting->default_latitude ?: -1.610123), 6),
                    'longitude' => round((float) ($setting->default_longitude ?: 103.613120), 6),
                ];
                $locationSource = 'Koordinat Default Sistem';
            }

            $aiData = $this->openRouterService->parseAndGenerateDraft(
                $captionPrompt,
                [[
                    'latitude' => $coords['latitude'],
                    'longitude' => $coords['longitude'],
                    'name' => $locationSource,
                ]],
                $setting
            );

            $matchedInterests = [];
            if (! empty($aiData['interest_keywords']) && $setting->hasMetaCredentials()) {
                $matchedInterests = $this->metaService->searchInterests($aiData['interest_keywords'], $setting);
            }

            $draft = AdDraft::create([
                'telegram_chat_id' => $chatId,
                'title' => $aiData['title'],
                'raw_user_prompt' => $captionPrompt,
                'video_local_path' => $localVideoPath,
                'target_platforms' => $aiData['target_platforms'] ?? ['META', 'TIKTOK', 'GOOGLE'],
                'objective' => $aiData['objective'],
                'destination_type' => $aiData['destination_type'],
                'destination_url' => $aiData['destination_url'],
                'daily_budget' => $aiData['daily_budget'],
                'age_min' => $aiData['age_min'],
                'age_max' => $aiData['age_max'],
                'genders' => $aiData['genders'],
                'custom_locations' => $aiData['custom_locations'],
                'excluded_locations' => [],
                'interests' => $matchedInterests,
                'ai_primary_text' => $aiData['ai_primary_text'],
                'ai_headline' => $aiData['ai_headline'],
                'ai_description' => $aiData['ai_description'],
                'cta_type' => $aiData['cta_type'],
                'status' => AdDraft::STATUS_DRAFT,
                'tiktok_ad_text' => $aiData['tiktok_ad_text'] ?? null,
                'tiktok_location_ids' => [(string) ($setting->tiktok_default_location_id ?: '1643084')],
                'tiktok_status' => AdDraft::TIKTOK_STATUS_DRAFT,
                'google_headline' => $aiData['google_headline'] ?? null,
                'google_long_headline' => $aiData['google_long_headline'] ?? null,
                'google_description' => $aiData['google_description'] ?? null,
                'google_status' => AdDraft::GOOGLE_STATUS_DRAFT,
            ]);

            $summaryMessage = $this->formatDraftSummaryMessage($draft, $locationSource);
            $inlineKeyboard = $this->buildDraftInlineKeyboard($draft->id);

            $this->sendMessage($chatId, $summaryMessage, $inlineKeyboard, $setting);
        } catch (\Throwable $e) {
            Log::error('Telegram processVideoAndCommand error', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            $this->sendMessage(
                $chatId,
                "❌ *Gagal memproses draft iklan:*\n`" . $e->getMessage() . "`",
                null,
                $setting
            );
        }
    }

    protected function buildDraftInlineKeyboard(int $draftId): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🚀 Terbitkan ke Meta (Mode Paused)', 'callback_data' => "publish:{$draftId}"],
                ],
                [
                    ['text' => '🎵 Terbitkan TikTok', 'callback_data' => "publish_tt:{$draftId}"],
                    ['text' => '▶️ Terbitkan Google', 'callback_data' => "publish_gg:{$draftId}"],
                ],
                [
                    ['text' => '🌐 Terbitkan ke Semua (Meta+TikTok+Google)', 'callback_data' => "publish_all:{$draftId}"],
                ],
                [
                    ['text' => '🔄 Buat Ulang Copywriting', 'callback_data' => "regen:{$draftId}"],
                    ['text' => '❌ Batalkan', 'callback_data' => "cancel:{$draftId}"],
                ],
            ],
        ];
    }

    /**
     * Handle Inline Keyboard button presses.
     */
    protected function handleCallbackQuery(array $callbackQuery, Setting $setting): void
    {
        $callbackId = (string) ($callbackQuery['id'] ?? '');
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? '');
        $messageId = (int) ($callbackQuery['message']['message_id'] ?? 0);
        $data = (string) ($callbackQuery['data'] ?? '');

        if ($chatId === '' || ! $setting->isChatAllowed($chatId)) {
            $this->answerCallbackQuery($callbackId, 'Akses ditolak.', true, $setting);

            return;
        }

        [$action, $draftId] = array_pad(explode(':', $data, 2), 2, null);
        $draft = $draftId ? AdDraft::find((int) $draftId) : null;

        if (! $draft) {
            $this->answerCallbackQuery($callbackId, 'Data iklan tidak ditemukan.', true, $setting);

            return;
        }

        try {
            switch ($action) {
                case 'publish':
                    $draft->update([
                        'status' => AdDraft::STATUS_UPLOADING_VIDEO,
                        'telegram_chat_id' => $chatId,
                    ]);
                    PublishMetaAdJob::dispatch($draft->id, $chatId);
                    $this->answerCallbackQuery($callbackId, 'Video sedang diunggah ke Meta Ads...', false, $setting);
                    $this->sendMessage(
                        $chatId,
                        "🚀 *Penerbitan Meta Ads Dimulai (Draft #{$draft->id})*\nVideo sedang diunggah ke Meta Ads di latar belakang (Queue).",
                        null,
                        $setting
                    );
                    break;

                case 'publish_tt':
                    $draft->update([
                        'tiktok_status' => AdDraft::TIKTOK_STATUS_UPLOADING,
                        'telegram_chat_id' => $chatId,
                    ]);
                    PublishTiktokAdJob::dispatch($draft->id, $chatId);
                    $this->answerCallbackQuery($callbackId, 'Video sedang diunggah ke TikTok Ads...', false, $setting);
                    $this->sendMessage(
                        $chatId,
                        "🎵 *Penerbitan TikTok Ads Dimulai (Draft #{$draft->id})*\nVideo sedang diunggah ke TikTok Business API v1.3 di latar belakang.",
                        null,
                        $setting
                    );
                    break;

                case 'publish_gg':
                    $draft->update([
                        'google_status' => AdDraft::GOOGLE_STATUS_UPLOADING_YT,
                        'telegram_chat_id' => $chatId,
                    ]);
                    PublishGoogleAdJob::dispatch($draft->id, $chatId);
                    $this->answerCallbackQuery($callbackId, 'Video sedang diproses ke YouTube & Google Ads...', false, $setting);
                    $this->sendMessage(
                        $chatId,
                        "▶️ *Penerbitan Google Ads Dimulai (Draft #{$draft->id})*\nVideo sedang diunggah ke YouTube (Unlisted) & dirakit di Google Ads API v18.",
                        null,
                        $setting
                    );
                    break;

                case 'publish_all':
                    $draft->update([
                        'target_platforms' => ['META', 'TIKTOK', 'GOOGLE'],
                        'status' => AdDraft::STATUS_UPLOADING_VIDEO,
                        'tiktok_status' => AdDraft::TIKTOK_STATUS_UPLOADING,
                        'google_status' => AdDraft::GOOGLE_STATUS_UPLOADING_YT,
                        'telegram_chat_id' => $chatId,
                    ]);
                    PublishMetaAdJob::dispatch($draft->id, $chatId);
                    PublishTiktokAdJob::dispatch($draft->id, $chatId);
                    PublishGoogleAdJob::dispatch($draft->id, $chatId);

                    $this->answerCallbackQuery($callbackId, 'Menerbitkan ke Meta, TikTok & Google Ads...', false, $setting);
                    $this->sendMessage(
                        $chatId,
                        "🌐 *Penerbitan Omni-Channel Dimulai (Draft #{$draft->id})*\nKetiga Job Queue (*Meta Ads*, *TikTok Ads*, dan *Google Ads*) sedang berjalan di latar belakang!",
                        null,
                        $setting
                    );
                    break;

                case 'regen':
                    $this->answerCallbackQuery($callbackId, 'Membuat ulang copywriting dengan AI...', false, $setting);
                    $newCopy = $this->openRouterService->regenerateCopywriting($draft, $setting);
                    $draft->update([
                        'ai_primary_text' => $newCopy['ai_primary_text'],
                        'ai_headline' => $newCopy['ai_headline'],
                        'ai_description' => $newCopy['ai_description'],
                        'tiktok_ad_text' => $newCopy['tiktok_ad_text'] ?? $draft->tiktok_ad_text,
                        'google_headline' => $newCopy['google_headline'] ?? $draft->google_headline,
                        'google_long_headline' => $newCopy['google_long_headline'] ?? $draft->google_long_headline,
                        'google_description' => $newCopy['google_description'] ?? $draft->google_description,
                    ]);
                    $draft->refresh();

                    $summaryMessage = $this->formatDraftSummaryMessage($draft, 'Diperbarui AI');
                    $inlineKeyboard = $this->buildDraftInlineKeyboard($draft->id);

                    if ($messageId > 0) {
                        $this->editMessageText($chatId, $messageId, $summaryMessage, $inlineKeyboard, $setting);
                    } else {
                        $this->sendMessage($chatId, $summaryMessage, $inlineKeyboard, $setting);
                    }
                    break;

                case 'cancel':
                    $title = $draft->title;
                    $draft->delete();
                    $this->answerCallbackQuery($callbackId, 'Draft iklan dibatalkan.', false, $setting);
                    if ($messageId > 0) {
                        $this->editMessageText(
                            $chatId,
                            $messageId,
                            "🗑️ Draft iklan *{$title}* telah dibatalkan dan dihapus.",
                            null,
                            $setting
                        );
                    }
                    break;

                case 'activate':
                    $this->answerCallbackQuery($callbackId, 'Mengaktifkan iklan di Meta Ads...', false, $setting);
                    $this->metaService->updateStatus($draft, 'ACTIVE', $setting);
                    $this->sendMessage(
                        $chatId,
                        "🔥 *META ADS AKTIF (LIVE)!*\n📌 Kampanye: *{$draft->title}*\n🆔 Campaign ID: `{$draft->meta_campaign_id}`",
                        ['inline_keyboard' => [[['text' => '⏸️ Pause Meta Ads', 'callback_data' => "pause:{$draft->id}"]]]],
                        $setting
                    );
                    break;

                case 'pause':
                    $this->answerCallbackQuery($callbackId, 'Mem-pause iklan di Meta Ads...', false, $setting);
                    $this->metaService->updateStatus($draft, 'PAUSED', $setting);
                    $this->sendMessage(
                        $chatId,
                        "⏸️ *Meta Ads Dijeda (PAUSED)*\n📌 Kampanye: *{$draft->title}*\n🆔 Campaign ID: `{$draft->meta_campaign_id}`",
                        ['inline_keyboard' => [[['text' => '🔥 Aktifkan Meta Ads', 'callback_data' => "activate:{$draft->id}"]]]],
                        $setting
                    );
                    break;

                case 'activate_tt':
                    $this->answerCallbackQuery($callbackId, 'Mengaktifkan iklan di TikTok Ads...', false, $setting);
                    $this->tiktokService->updateStatus($draft, 'ACTIVE', $setting);
                    $this->sendMessage(
                        $chatId,
                        "🔥 *TIKTOK ADS AKTIF (LIVE)!*\n📌 Kampanye: *{$draft->title}*\n🆔 TikTok Campaign ID: `{$draft->tiktok_campaign_id}`",
                        ['inline_keyboard' => [[['text' => '⏸️ Pause TikTok Ads', 'callback_data' => "pause_tt:{$draft->id}"]]]],
                        $setting
                    );
                    break;

                case 'pause_tt':
                    $this->answerCallbackQuery($callbackId, 'Mem-pause iklan di TikTok Ads...', false, $setting);
                    $this->tiktokService->updateStatus($draft, 'PAUSED', $setting);
                    $this->sendMessage(
                        $chatId,
                        "⏸️ *TikTok Ads Dijeda (DISABLE)*\n📌 Kampanye: *{$draft->title}*\n🆔 TikTok Campaign ID: `{$draft->tiktok_campaign_id}`",
                        ['inline_keyboard' => [[['text' => '🔥 Aktifkan TikTok Ads', 'callback_data' => "activate_tt:{$draft->id}"]]]],
                        $setting
                    );
                    break;

                case 'activate_gg':
                    $this->answerCallbackQuery($callbackId, 'Mengaktifkan iklan di Google Ads...', false, $setting);
                    $this->googleService->updateStatus($draft, 'ACTIVE', $setting);
                    $this->sendMessage(
                        $chatId,
                        "🔥 *GOOGLE ADS AKTIF (ENABLED)!*\n📌 Kampanye: *{$draft->title}*\n🆔 Google Campaign ID: `{$draft->google_campaign_id}`",
                        ['inline_keyboard' => [[['text' => '⏸️ Pause Google Ads', 'callback_data' => "pause_gg:{$draft->id}"]]]],
                        $setting
                    );
                    break;

                case 'pause_gg':
                    $this->answerCallbackQuery($callbackId, 'Mem-pause iklan di Google Ads...', false, $setting);
                    $this->googleService->updateStatus($draft, 'PAUSED', $setting);
                    $this->sendMessage(
                        $chatId,
                        "⏸️ *Google Ads Dijeda (PAUSED)*\n📌 Kampanye: *{$draft->title}*\n🆔 Google Campaign ID: `{$draft->google_campaign_id}`",
                        ['inline_keyboard' => [[['text' => '🔥 Aktifkan Google Ads', 'callback_data' => "activate_gg:{$draft->id}"]]]],
                        $setting
                    );
                    break;

                case 'keep_paused':
                    $this->answerCallbackQuery($callbackId, 'Iklan tetap dalam mode PAUSED (aman).', false, $setting);
                    $this->sendMessage(
                        $chatId,
                        "✅ Iklan *{$draft->title}* tetap disimpan dalam status *PAUSED*. Anda dapat mengaktifkannya kapan saja melalui `/status` atau Dashboard Web.",
                        null,
                        $setting
                    );
                    break;
            }
        } catch (\Throwable $e) {
            $this->answerCallbackQuery($callbackId, 'Terjadi kesalahan.', true, $setting);
            $this->sendMessage(
                $chatId,
                "⚠️ *Gagal mengeksekusi aksi:*\n`" . $e->getMessage() . "`",
                null,
                $setting
            );
        }
    }

    /**
     * Handle /status command: list active & paused campaigns across Meta, TikTok, and Google Ads.
     */
    protected function handleStatusCommand(string $chatId, Setting $setting): void
    {
        $campaigns = AdDraft::query()
            ->where(function ($q) {
                $q->whereNotNull('meta_campaign_id')
                    ->orWhereNotNull('tiktok_campaign_id')
                    ->orWhereNotNull('google_campaign_id');
            })
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        if ($campaigns->isEmpty()) {
            $this->sendMessage(
                $chatId,
                "📭 Belum ada kampanye yang diterbitkan ke Meta, TikTok, maupun Google Ads.\nSilakan kirim video + caption perintah untuk membuat iklan baru.",
                null,
                $setting
            );

            return;
        }

        $this->sendMessage(
            $chatId,
            "📊 *DAFTAR KAMPANYE OMNI-CHANNEL ({$campaigns->count()} Terakhir)*\nMengambil metrik spend hari ini dari Meta, TikTok & Google API...",
            null,
            $setting
        );

        foreach ($campaigns as $draft) {
            $lines = ["📌 *{$draft->title}*", "💰 Budget Harian: {$draft->formatted_budget}"];
            $buttons = [];

            if (! empty($draft->meta_campaign_id)) {
                $mIns = $this->metaService->getInsights((string) $draft->meta_campaign_id, $setting, 'today');
                $mSpend = 'Rp ' . number_format((float) ($mIns['spend'] ?? 0), 0, ',', '.');
                $mActive = $draft->status === AdDraft::STATUS_ACTIVE_ON_META;
                $lines[] = "📘 *Meta Ads:* " . ($mActive ? '🟢 ACTIVE' : '🟡 PAUSED') . " | Spend: *{$mSpend}* | Klik: {$mIns['clicks']}";
                $buttons[] = [
                    $mActive
                        ? ['text' => '⏸️ Pause Meta', 'callback_data' => "pause:{$draft->id}"]
                        : ['text' => '🔥 Aktifkan Meta', 'callback_data' => "activate:{$draft->id}"],
                ];
            }

            if (! empty($draft->tiktok_campaign_id)) {
                $tIns = $this->tiktokService->getInsights((string) $draft->tiktok_campaign_id, $setting, 'today');
                $tSpend = 'Rp ' . number_format((float) ($tIns['spend'] ?? 0), 0, ',', '.');
                $tActive = $draft->tiktok_status === AdDraft::TIKTOK_STATUS_ACTIVE;
                $lines[] = "🎵 *TikTok Ads:* " . ($tActive ? '🟢 ACTIVE' : '🟡 PAUSED') . " | Spend: *{$tSpend}* | Klik: {$tIns['clicks']}";
                $buttons[] = [
                    $tActive
                        ? ['text' => '⏸️ Pause TikTok', 'callback_data' => "pause_tt:{$draft->id}"]
                        : ['text' => '🔥 Aktifkan TikTok', 'callback_data' => "activate_tt:{$draft->id}"],
                ];
            }

            if (! empty($draft->google_campaign_id)) {
                $gIns = $this->googleService->getInsights((string) $draft->google_campaign_id, $setting, 'today');
                $gSpend = 'Rp ' . number_format((float) ($gIns['spend'] ?? 0), 0, ',', '.');
                $gActive = $draft->google_status === AdDraft::GOOGLE_STATUS_ACTIVE;
                $lines[] = "▶️ *Google Ads:* " . ($gActive ? '🟢 ACTIVE' : '🟡 PAUSED') . " | Spend: *{$gSpend}* | Klik: {$gIns['clicks']}";
                $buttons[] = [
                    $gActive
                        ? ['text' => '⏸️ Pause Google', 'callback_data' => "pause_gg:{$draft->id}"]
                        : ['text' => '🔥 Aktifkan Google', 'callback_data' => "activate_gg:{$draft->id}"],
                ];
            }

            $this->sendMessage(
                $chatId,
                implode("\n", $lines),
                ! empty($buttons) ? ['inline_keyboard' => $buttons] : null,
                $setting
            );
        }
    }

    protected function handleStartCommand(string $chatId, Setting $setting): void
    {
        $cachedLoc = Cache::get($this->locationCacheKey($chatId));
        $locText = $cachedLoc
            ? "{$cachedLoc['latitude']}, {$cachedLoc['longitude']} (ShareLoc Aktif)"
            : "{$setting->default_latitude}, {$setting->default_longitude} (Default)";

        $msg = "👋 *Selamat Datang di Omni-Channel AI Ads Publisher Bot!*\n"
            . "_(Meta Ads v21.0 + TikTok Ads v1.3 + Google Ads v18)_\n\n"
            . "🆔 Chat ID Anda: `{$chatId}`\n"
            . "📍 Titik Lokasi Saat Ini: `{$locText}`\n\n"
            . "*Cara Membuat Iklan Otomatis:*\n"
            . "1️⃣ Kirim *Share Location* (opsional) untuk mengunci titik koordinat target.\n"
            . "2️⃣ Kirim *Video Iklan* beserta caption bahasa sehari-hari.\n"
            . "3️⃣ AI akan otomatis menyusun copywriting untuk *Meta*, *TikTok*, dan *Google/YouTube*.\n\n"
            . "*Perintah Tersedia:*\n"
            . "• `/status` — Lihat daftar iklan aktif di Meta, TikTok & Google beserta spend hari ini.";

        $this->sendMessage($chatId, $msg, null, $setting);
    }

    public function notifyPublishSuccess(AdDraft $draft, string $chatId, ?Setting $setting = null): void
    {
        $setting = $setting ?? Setting::current();

        $text = "✅ *IKLAN BERHASIL DITERBITKAN KE META ADS (MODE PAUSED)*\n\n"
            . "📌 *Judul:* {$draft->title}\n"
            . "🎯 *Objective:* `{$draft->objective}` ({$draft->destination_type})\n"
            . "💰 *Budget Harian:* {$draft->formatted_budget}\n"
            . "🎬 *Meta Video ID:* `{$draft->meta_video_id}`\n"
            . "📁 *Campaign ID:* `{$draft->meta_campaign_id}`\n"
            . "🗂️ *AdSet ID:* `{$draft->meta_adset_id}`\n"
            . "🎨 *Creative ID:* `{$draft->meta_creative_id}`\n"
            . "📢 *Ad ID:* `{$draft->meta_ad_id}`\n\n"
            . "Saat ini iklan berstatus *PAUSED (Draft Aman)*. Apakah Anda ingin langsung mengaktifkannya sekarang?";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔥 Aktifkan Iklan Sekarang (LIVE)', 'callback_data' => "activate:{$draft->id}"]],
                [['text' => '⏸️ Biarkan Paused', 'callback_data' => "keep_paused:{$draft->id}"]],
            ],
        ];

        $this->sendMessage($chatId, $text, $keyboard, $setting);
    }

    public function notifyPublishFailed(AdDraft $draft, string $chatId, string $errorMessage, ?Setting $setting = null): void
    {
        $setting = $setting ?? Setting::current();

        $text = "❌ *GAGAL MENERBITKAN KE META ADS*\n\n"
            . "📌 *Judul:* {$draft->title}\n"
            . "⚠️ *Detail Error:*\n`" . Str::limit($errorMessage, 600) . "`";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔄 Coba Lagi Meta Ads (Retry)', 'callback_data' => "publish:{$draft->id}"]],
            ],
        ];

        $this->sendMessage($chatId, $text, $keyboard, $setting);
    }

    public function notifyTiktokPublishSuccess(AdDraft $draft, string $chatId, ?Setting $setting = null): void
    {
        $setting = $setting ?? Setting::current();

        $text = "✅ *IKLAN BERHASIL DITERBITKAN KE TIKTOK ADS (MODE PAUSED)*\n\n"
            . "📌 *Judul:* {$draft->title}\n"
            . "🎬 *TikTok Video ID:* `{$draft->tiktok_video_id}`\n"
            . "📁 *Campaign ID:* `{$draft->tiktok_campaign_id}`\n"
            . "🗂️ *AdGroup ID:* `{$draft->tiktok_adgroup_id}`\n"
            . "📢 *Ad ID:* `{$draft->tiktok_ad_id}`\n\n"
            . "Ingin langsung mengaktifkan iklan TikTok ini sekarang?";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔥 Aktifkan TikTok Ads Sekarang (LIVE)', 'callback_data' => "activate_tt:{$draft->id}"]],
                [['text' => '⏸️ Biarkan Paused', 'callback_data' => "keep_paused:{$draft->id}"]],
            ],
        ];

        $this->sendMessage($chatId, $text, $keyboard, $setting);
    }

    public function notifyTiktokPublishFailed(AdDraft $draft, string $chatId, string $errorMessage, ?Setting $setting = null): void
    {
        $setting = $setting ?? Setting::current();

        $text = "❌ *GAGAL MENERBITKAN KE TIKTOK ADS*\n\n"
            . "📌 *Judul:* {$draft->title}\n"
            . "⚠️ *Detail Error:*\n`" . Str::limit($errorMessage, 600) . "`";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔄 Coba Lagi TikTok Ads (Retry)', 'callback_data' => "publish_tt:{$draft->id}"]],
            ],
        ];

        $this->sendMessage($chatId, $text, $keyboard, $setting);
    }

    public function notifyGooglePublishSuccess(AdDraft $draft, string $chatId, ?Setting $setting = null): void
    {
        $setting = $setting ?? Setting::current();

        $text = "✅ *IKLAN BERHASIL DITERBITKAN KE GOOGLE ADS (MODE PAUSED)*\n\n"
            . "📌 *Judul:* {$draft->title}\n"
            . "▶️ *YouTube Video ID:* `{$draft->youtube_video_id}`\n"
            . "📁 *Google Campaign ID:* `{$draft->google_campaign_id}`\n"
            . "🗂️ *Google AdGroup ID:* `{$draft->google_adgroup_id}`\n"
            . "📢 *Google Ad ID:* `{$draft->google_ad_id}`\n\n"
            . "Ingin langsung mengaktifkan iklan Google/YouTube ini sekarang?";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔥 Aktifkan Google Ads Sekarang (LIVE)', 'callback_data' => "activate_gg:{$draft->id}"]],
                [['text' => '⏸️ Biarkan Paused', 'callback_data' => "keep_paused:{$draft->id}"]],
            ],
        ];

        $this->sendMessage($chatId, $text, $keyboard, $setting);
    }

    public function notifyGooglePublishFailed(AdDraft $draft, string $chatId, string $errorMessage, ?Setting $setting = null): void
    {
        $setting = $setting ?? Setting::current();

        $text = "❌ *GAGAL MENERBITKAN KE GOOGLE ADS*\n\n"
            . "📌 *Judul:* {$draft->title}\n"
            . "⚠️ *Detail Error:*\n`" . Str::limit($errorMessage, 600) . "`";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔄 Coba Lagi Google Ads (Retry)', 'callback_data' => "publish_gg:{$draft->id}"]],
            ],
        ];

        $this->sendMessage($chatId, $text, $keyboard, $setting);
    }

    protected function formatDraftSummaryMessage(AdDraft $draft, string $locationSource): string
    {
        $locations = $draft->custom_locations ?? [];
        $locLines = [];
        foreach ($locations as $loc) {
            $locLines[] = sprintf(
                "• `%s, %s` (Radius *%s km*)",
                $loc['latitude'] ?? 0,
                $loc['longitude'] ?? 0,
                $loc['radius'] ?? 10
            );
        }
        $locText = implode("\n", $locLines);

        $interests = $draft->interests ?? [];
        $interestNames = array_map(fn ($i) => $i['name'] ?? '', $interests);
        $interestText = ! empty($interestNames)
            ? implode(', ', $interestNames)
            : 'Broad / Tanpa Spesifik Interest';

        return "✨ *DRAFT IKLAN AI OMNI-CHANNEL SIAP (#{$draft->id})*\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "📌 *Nama Kampanye:* {$draft->title}\n"
            . "🎯 *Tujuan:* {$draft->destination_type} (`{$draft->objective}`)\n"
            . "💰 *Budget Harian:* {$draft->formatted_budget}/hari\n"
            . "👥 *Target Usia:* {$draft->age_min} – {$draft->age_max} Tahun ({$draft->gender_label})\n"
            . "📍 *Titik Lokasi ({$locationSource}):*\n{$locText}\n"
            . "💡 *Interest:* {$interestText}\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "📘 *META PRIMARY TEXT:*\n{$draft->ai_primary_text}\n"
            . "🏷️ *META HEADLINE:* *{$draft->ai_headline}*\n\n"
            . "🎵 *TIKTOK CAPTION:*\n_{$draft->tiktok_ad_text}_\n\n"
            . "▶️ *GOOGLE / YOUTUBE HEADLINE:*\n*{$draft->google_headline}* — {$draft->google_long_headline}\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "Pilih platform penerbitan di bawah ini:";
    }

    protected function downloadTelegramVideo(string $fileId, Setting $setting): string
    {
        $token = trim((string) $setting->telegram_bot_token);

        $fileInfoResp = Http::timeout(30)->get("https://api.telegram.org/bot{$token}/getFile", [
            'file_id' => $fileId,
        ]);

        if (! $fileInfoResp->successful() || ! $fileInfoResp->json('ok')) {
            throw new RuntimeException(
                'Gagal mengambil info file video dari Telegram (kemungkinan ukuran melebihi batas 20MB Bot API): '
                . ($fileInfoResp->json('description') ?? $fileInfoResp->body())
            );
        }

        $remoteFilePath = (string) ($fileInfoResp->json('result.file_path') ?? '');
        if ($remoteFilePath === '') {
            throw new RuntimeException('Telegram tidak mengembalikan file_path untuk video ini.');
        }

        $ext = strtolower(pathinfo($remoteFilePath, PATHINFO_EXTENSION)) ?: 'mp4';
        $fileName = 'tg_video_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.' . $ext;
        $relativeStoragePath = 'ads_videos/' . $fileName;

        Storage::disk('public')->makeDirectory('ads_videos');
        $destinationFullPath = Storage::disk('public')->path($relativeStoragePath);

        $downloadResp = Http::timeout(300)
            ->sink($destinationFullPath)
            ->get("https://api.telegram.org/file/bot{$token}/{$remoteFilePath}");

        if (! $downloadResp->successful() || ! file_exists($destinationFullPath)) {
            throw new RuntimeException('Gagal mengunduh file video dari server Telegram.');
        }

        return $relativeStoragePath;
    }

    protected function extractVideoFileId(array $message): ?string
    {
        if (! empty($message['video']['file_id'])) {
            return (string) $message['video']['file_id'];
        }

        if (! empty($message['document']['file_id'])) {
            $mime = (string) ($message['document']['mime_type'] ?? '');
            if (str_starts_with($mime, 'video/')) {
                return (string) $message['document']['file_id'];
            }
        }

        return null;
    }

    public function sendMessage(
        string|int $chatId,
        string $text,
        ?array $replyMarkup = null,
        ?Setting $setting = null
    ): void {
        $setting = $setting ?? Setting::current();
        $token = trim((string) $setting->telegram_bot_token);

        if ($token === '') {
            return;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        $response = Http::timeout(30)->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);

        if (! $response->successful()) {
            unset($payload['parse_mode']);
            Http::timeout(30)->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
        }
    }

    public function editMessageText(
        string|int $chatId,
        int $messageId,
        string $text,
        ?array $replyMarkup = null,
        ?Setting $setting = null
    ): void {
        $setting = $setting ?? Setting::current();
        $token = trim((string) $setting->telegram_bot_token);

        if ($token === '') {
            return;
        }

        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        $response = Http::timeout(30)->post("https://api.telegram.org/bot{$token}/editMessageText", $payload);
        if (! $response->successful()) {
            unset($payload['parse_mode']);
            Http::timeout(30)->post("https://api.telegram.org/bot{$token}/editMessageText", $payload);
        }
    }

    public function answerCallbackQuery(
        string $callbackQueryId,
        string $text = '',
        bool $showAlert = false,
        ?Setting $setting = null
    ): void {
        $setting = $setting ?? Setting::current();
        $token = trim((string) $setting->telegram_bot_token);

        if ($token === '' || $callbackQueryId === '') {
            return;
        }

        Http::timeout(15)->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]);
    }

    protected function locationCacheKey(string $chatId): string
    {
        return "telegram_shareloc_{$chatId}";
    }

    protected function videoCacheKey(string $chatId): string
    {
        return "telegram_pending_video_{$chatId}";
    }
}
