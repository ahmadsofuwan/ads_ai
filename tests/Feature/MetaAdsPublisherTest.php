<?php

namespace Tests\Feature;

use App\Jobs\PublishGoogleAdJob;
use App\Jobs\PublishMetaAdJob;
use App\Jobs\PublishTiktokAdJob;
use App\Models\AdAccount;
use App\Models\AdDraft;
use App\Models\AiProvider;
use App\Models\Setting;
use App\Services\GoogleAdsService;
use App\Services\MetaMarketingService;
use App\Services\OpenRouterService;
use App\Services\TelegramBotService;
use App\Services\TikTokMarketingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetaAdsPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_model_auto_prefixes_act_and_strips_google_customer_id_dashes(): void
    {
        $setting = Setting::current();
        $setting->update([
            'meta_ad_account_id' => '987654321',
            'google_customer_id' => '123-456-7890',
            'allowed_telegram_chat_ids' => ['111222', '333444'],
        ]);

        $fresh = $setting->fresh();
        $this->assertSame('act_987654321', $fresh->meta_ad_account_id);
        $this->assertSame('1234567890', $fresh->google_customer_id);
        $this->assertTrue($fresh->isChatAllowed('111222'));
        $this->assertFalse($fresh->isChatAllowed('999999'));
    }

    public function test_openrouter_service_extracts_parameters_and_generates_omni_channel_copywriting(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'title' => 'Promo WiFi Cepat - Jambi',
                                'daily_budget' => 50000,
                                'age_min' => 24,
                                'age_max' => 45,
                                'genders' => [],
                                'destination_type' => 'WHATSAPP',
                                'destination_url' => '',
                                'objective' => 'OUTCOME_ENGAGEMENT',
                                'cta_type' => 'WHATSAPP_MESSAGE',
                                'radius_km' => 12,
                                'interest_keywords' => ['Internet', 'Wi-Fi'],
                                'target_platforms' => ['META', 'TIKTOK', 'GOOGLE'],
                                'ai_primary_text' => "🔥 Hook Kuat!\n✅ Cepat\n📲 Chat WA sekarang!",
                                'ai_headline' => 'Pasang WiFi Cepat Sekarang',
                                'ai_description' => 'Gratis Instalasi Hari Ini',
                                'tiktok_ad_text' => '🔥 Pasang WiFi cepat tanpa FUP! Klik sekarang #wifi #promo',
                                'google_headline' => 'Pasang WiFi Cepat',
                                'google_long_headline' => 'Pasang WiFi Rumah Cepat Unlimited — Gratis Instalasi!',
                                'google_description' => 'Hubungi kami via WhatsApp untuk pemasangan hari ini.',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $setting = Setting::current();
        $setting->update(['openrouter_api_key' => 'sk-or-test-key']);

        $service = app(OpenRouterService::class);
        $result = $service->parseAndGenerateDraft(
            'Promo WiFi rumah radius 12km umur 24-45 tahun budget 50rb/hari ke WA',
            [['latitude' => -1.610123, 'longitude' => 103.613120]],
            $setting
        );

        $this->assertSame(50000, $result['daily_budget']);
        $this->assertSame(24, $result['age_min']);
        $this->assertSame(45, $result['age_max']);
        $this->assertSame(12, $result['radius_km']);
        $this->assertSame('WHATSAPP', $result['destination_type']);
        $this->assertSame('OUTCOME_ENGAGEMENT', $result['objective']);
        $this->assertLessThanOrEqual(40, mb_strlen($result['ai_headline']));
        $this->assertLessThanOrEqual(100, mb_strlen($result['tiktok_ad_text']));
        $this->assertLessThanOrEqual(30, mb_strlen($result['google_headline']));
    }

    public function test_publish_meta_ad_job_executes_full_v21_pipeline_in_paused_mode(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('ads_videos/test.mp4', 'fake-video-binary-content');

        $setting = Setting::current();
        $setting->update([
            'meta_access_token' => 'EAAB_TEST_TOKEN',
            'meta_ad_account_id' => '123456789',
            'meta_page_id' => '1122334455',
            'meta_whatsapp_number' => '628123456789',
            'telegram_bot_token' => '123456:ABC-DEF',
        ]);

        Http::fake([
            'https://graph.facebook.com/v21.0/act_123456789/advideos' => Http::response(['id' => 'vid_9001'], 200),
            'https://graph.facebook.com/v21.0/vid_9001*' => Http::response([
                'status' => ['video_status' => 'ready'],
                'thumbnails' => [
                    'data' => [
                        ['uri' => 'https://scontent.xx.fbcdn.net/thumb1.jpg', 'is_preferred' => true],
                    ],
                ],
            ], 200),
            'https://graph.facebook.com/v21.0/act_123456789/campaigns' => Http::response(['id' => 'camp_9002'], 200),
            'https://graph.facebook.com/v21.0/act_123456789/adsets' => Http::response(['id' => 'adset_9003'], 200),
            'https://graph.facebook.com/v21.0/act_123456789/adcreatives' => Http::response(['id' => 'creative_9004'], 200),
            'https://graph.facebook.com/v21.0/act_123456789/ads' => Http::response(['id' => 'ad_9005'], 200),
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $draft = AdDraft::create([
            'telegram_chat_id' => '777888',
            'title' => 'Test Meta Campaign',
            'raw_user_prompt' => 'Test prompt',
            'video_local_path' => 'ads_videos/test.mp4',
            'objective' => AdDraft::OBJECTIVE_ENGAGEMENT,
            'destination_type' => AdDraft::DESTINATION_WHATSAPP,
            'daily_budget' => 50000,
            'age_min' => 21,
            'age_max' => 50,
            'genders' => [],
            'custom_locations' => [
                ['latitude' => -1.610123, 'longitude' => 103.613120, 'radius' => 10, 'distance_unit' => 'kilometer'],
            ],
            'excluded_locations' => [],
            'interests' => [['id' => '6003139266461', 'name' => 'Internet']],
            'ai_primary_text' => 'Primary Text Test',
            'ai_headline' => 'Headline Test',
            'ai_description' => 'Description Test',
            'cta_type' => AdDraft::CTA_WHATSAPP_MESSAGE,
            'status' => AdDraft::STATUS_DRAFT,
        ]);

        $job = new PublishMetaAdJob($draft->id, '777888');
        $job->handle(app(MetaMarketingService::class), app(TelegramBotService::class));

        $draft->refresh();
        $this->assertSame('vid_9001', $draft->meta_video_id);
        $this->assertSame('camp_9002', $draft->meta_campaign_id);
        $this->assertSame(AdDraft::STATUS_PAUSED_ON_META, $draft->status);
    }

    public function test_publish_tiktok_ad_job_executes_v13_pipeline_in_paused_mode(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('ads_videos/test_tt.mp4', 'fake-tiktok-video-bytes');

        $setting = Setting::current();
        $setting->update([
            'tiktok_access_token' => 'TT_TEST_TOKEN',
            'tiktok_advertiser_id' => '7000111222',
            'tiktok_identity_id' => 'id_999',
        ]);

        Http::fake([
            'https://business-api.tiktok.com/open_api/v1.3/file/video/ad/upload/' => Http::response([
                'code' => 0,
                'data' => [
                    ['video_id' => 'v_tt_101', 'video_cover_url' => 'https://p16.tiktokcdn.com/cover.jpg'],
                ],
            ], 200),
            'https://business-api.tiktok.com/open_api/v1.3/file/image/ad/upload/' => Http::response([
                'code' => 0,
                'data' => ['image_id' => 'img_tt_102'],
            ], 200),
            'https://business-api.tiktok.com/open_api/v1.3/campaign/create/' => Http::response([
                'code' => 0,
                'data' => ['campaign_id' => 'camp_tt_103'],
            ], 200),
            'https://business-api.tiktok.com/open_api/v1.3/adgroup/create/' => Http::response([
                'code' => 0,
                'data' => ['adgroup_id' => 'adgrp_tt_104'],
            ], 200),
            'https://business-api.tiktok.com/open_api/v1.3/ad/create/' => Http::response([
                'code' => 0,
                'data' => ['ad_ids' => ['ad_tt_105']],
            ], 200),
        ]);

        $draft = AdDraft::create([
            'title' => 'Test TikTok Campaign',
            'raw_user_prompt' => 'Test TikTok',
            'video_local_path' => 'ads_videos/test_tt.mp4',
            'target_platforms' => ['TIKTOK'],
            'objective' => AdDraft::OBJECTIVE_TRAFFIC,
            'destination_type' => AdDraft::DESTINATION_WHATSAPP,
            'daily_budget' => 50000,
            'age_min' => 21,
            'age_max' => 45,
            'custom_locations' => [
                ['latitude' => -1.610123, 'longitude' => 103.613120, 'radius' => 10, 'distance_unit' => 'kilometer'],
            ],
            'ai_primary_text' => 'Primary Text',
            'ai_headline' => 'Headline',
            'tiktok_ad_text' => 'Caption TikTok #promo',
            'cta_type' => AdDraft::CTA_WHATSAPP_MESSAGE,
        ]);

        $job = new PublishTiktokAdJob($draft->id);
        $job->handle(app(TikTokMarketingService::class), app(TelegramBotService::class));

        $draft->refresh();
        $this->assertSame('v_tt_101', $draft->tiktok_video_id);
        $this->assertSame('img_tt_102', $draft->tiktok_image_id);
        $this->assertSame('camp_tt_103', $draft->tiktok_campaign_id);
        $this->assertSame('adgrp_tt_104', $draft->tiktok_adgroup_id);
        $this->assertSame('ad_tt_105', $draft->tiktok_ad_id);
        $this->assertSame(AdDraft::TIKTOK_STATUS_PAUSED, $draft->tiktok_status);
    }

    public function test_publish_google_ad_job_executes_v18_and_youtube_pipeline_in_paused_mode(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('ads_videos/test_gg.mp4', 'fake-google-video-bytes');

        $setting = Setting::current();
        $setting->update([
            'google_developer_token' => 'DEV_TOKEN_123',
            'google_client_id' => 'client_123.apps.googleusercontent.com',
            'google_client_secret' => 'secret_123',
            'google_refresh_token' => 'refresh_123',
            'google_customer_id' => '123-456-7890',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.mock_token'], 200),
            'https://www.googleapis.com/upload/youtube/v3/videos*' => Http::response(['id' => 'dQw4w9WgXcQ'], 200),
            'https://googleads.googleapis.com/v18/customers/1234567890/assets:mutate' => Http::response([
                'results' => [['resourceName' => 'customers/1234567890/assets/501']],
            ], 200),
            'https://googleads.googleapis.com/v18/customers/1234567890/campaignBudgets:mutate' => Http::response([
                'results' => [['resourceName' => 'customers/1234567890/campaignBudgets/502']],
            ], 200),
            'https://googleads.googleapis.com/v18/customers/1234567890/campaigns:mutate' => Http::response([
                'results' => [['resourceName' => 'customers/1234567890/campaigns/503']],
            ], 200),
            'https://googleads.googleapis.com/v18/customers/1234567890/campaignCriteria:mutate' => Http::response([
                'results' => [['resourceName' => 'customers/1234567890/campaignCriteria/503~1']],
            ], 200),
            'https://googleads.googleapis.com/v18/customers/1234567890/adGroups:mutate' => Http::response([
                'results' => [['resourceName' => 'customers/1234567890/adGroups/504']],
            ], 200),
            'https://googleads.googleapis.com/v18/customers/1234567890/adGroupAds:mutate' => Http::response([
                'results' => [['resourceName' => 'customers/1234567890/adGroupAds/504~505']],
            ], 200),
        ]);

        $draft = AdDraft::create([
            'title' => 'Test Google Campaign',
            'raw_user_prompt' => 'Test Google',
            'video_local_path' => 'ads_videos/test_gg.mp4',
            'target_platforms' => ['GOOGLE'],
            'objective' => AdDraft::OBJECTIVE_TRAFFIC,
            'destination_type' => AdDraft::DESTINATION_WEBSITE,
            'destination_url' => 'https://example.com',
            'daily_budget' => 50000,
            'age_min' => 21,
            'age_max' => 50,
            'custom_locations' => [
                ['latitude' => -1.610123, 'longitude' => 103.613120, 'radius' => 10, 'distance_unit' => 'kilometer'],
            ],
            'ai_primary_text' => 'Primary Text',
            'ai_headline' => 'Headline',
            'google_headline' => 'Headline Google',
            'google_long_headline' => 'Long Headline Google Ads',
            'google_description' => 'Description Google Ads',
            'cta_type' => AdDraft::CTA_LEARN_MORE,
        ]);

        $job = new PublishGoogleAdJob($draft->id);
        $job->handle(app(GoogleAdsService::class), app(TelegramBotService::class));

        $draft->refresh();
        $this->assertSame('dQw4w9WgXcQ', $draft->youtube_video_id);
        $this->assertSame('503', $draft->google_campaign_id);
        $this->assertSame('504', $draft->google_adgroup_id);
        $this->assertSame('504~505', $draft->google_ad_id);
        $this->assertSame(AdDraft::GOOGLE_STATUS_PAUSED, $draft->google_status);
    }

    public function test_telegram_webhook_stores_share_location_in_cache(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $setting = Setting::current();
        $setting->update([
            'telegram_bot_token' => '123456:BOT_TOKEN',
            'allowed_telegram_chat_ids' => ['555666'],
        ]);

        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'message_id' => 10,
                'chat' => ['id' => 555666],
                'location' => [
                    'latitude' => -6.200000,
                    'longitude' => 106.816666,
                ],
            ],
        ]);

        $response->assertOk();
        $cached = Cache::get('telegram_shareloc_555666');
        $this->assertNotNull($cached);
        $this->assertEquals(-6.2, $cached['latitude']);
        $this->assertEquals(106.816666, $cached['longitude']);
    }

    public function test_all_web_dashboard_routes_render_successfully(): void
    {
        $this->seed();
        $draft = AdDraft::first();

        $this->get('/')->assertOk()->assertSee('Ringkasan Dashboard');
        $this->get('/ads/create')->assertOk()->assertSee('Studio Pembuat Iklan AI');
        $this->get('/ads')->assertOk()->assertSee('Manajemen Kampanye');
        $this->get('/ads/' . $draft->id)->assertOk()->assertSee($draft->title);
        $this->get('/settings')->assertOk()->assertSee('Pengaturan Sistem');
    }

    public function test_multi_ad_accounts_per_platform_publish_to_distinct_api_accounts(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('ads_videos/multi.mp4', 'fake-video-content');

        $metaAccountA = AdAccount::create([
            'name' => 'Meta BM Cabang Jambi',
            'provider' => AdAccount::PROVIDER_META,
            'is_default' => true,
            'is_active' => true,
            'meta_access_token' => 'TOKEN_META_A',
            'meta_ad_account_id' => '111111',
            'meta_page_id' => 'page_111',
        ]);

        $metaAccountB = AdAccount::create([
            'name' => 'Meta BM Cabang Palembang',
            'provider' => AdAccount::PROVIDER_META,
            'is_default' => false,
            'is_active' => true,
            'meta_access_token' => 'TOKEN_META_B',
            'meta_ad_account_id' => '222222',
            'meta_page_id' => 'page_222',
        ]);

        Http::fake([
            'https://graph.facebook.com/v21.0/act_111111/advideos' => Http::response(['id' => 'vid_A'], 200),
            'https://graph.facebook.com/v21.0/vid_A*' => Http::response([
                'status' => ['video_status' => 'ready'],
                'thumbnails' => ['data' => [['uri' => 'https://example.com/a.jpg', 'is_preferred' => true]]],
            ], 200),
            'https://graph.facebook.com/v21.0/act_111111/campaigns' => Http::response(['id' => 'camp_A'], 200),
            'https://graph.facebook.com/v21.0/act_111111/adsets' => Http::response(['id' => 'adset_A'], 200),
            'https://graph.facebook.com/v21.0/act_111111/adcreatives' => Http::response(['id' => 'creat_A'], 200),
            'https://graph.facebook.com/v21.0/act_111111/ads' => Http::response(['id' => 'ad_A'], 200),

            'https://graph.facebook.com/v21.0/act_222222/advideos' => Http::response(['id' => 'vid_B'], 200),
            'https://graph.facebook.com/v21.0/vid_B*' => Http::response([
                'status' => ['video_status' => 'ready'],
                'thumbnails' => ['data' => [['uri' => 'https://example.com/b.jpg', 'is_preferred' => true]]],
            ], 200),
            'https://graph.facebook.com/v21.0/act_222222/campaigns' => Http::response(['id' => 'camp_B'], 200),
            'https://graph.facebook.com/v21.0/act_222222/adsets' => Http::response(['id' => 'adset_B'], 200),
            'https://graph.facebook.com/v21.0/act_222222/adcreatives' => Http::response(['id' => 'creat_B'], 200),
            'https://graph.facebook.com/v21.0/act_222222/ads' => Http::response(['id' => 'ad_B'], 200),
        ]);

        $draftA = AdDraft::create([
            'title' => 'Iklan Cabang Jambi',
            'raw_user_prompt' => 'Test A',
            'video_local_path' => 'ads_videos/multi.mp4',
            'meta_account_id' => $metaAccountA->id,
            'custom_locations' => [['latitude' => -1.61, 'longitude' => 103.61, 'radius' => 10]],
            'ai_primary_text' => 'Copy A',
            'ai_headline' => 'Headline A',
        ]);

        $draftB = AdDraft::create([
            'title' => 'Iklan Cabang Palembang',
            'raw_user_prompt' => 'Test B',
            'video_local_path' => 'ads_videos/multi.mp4',
            'meta_account_id' => $metaAccountB->id,
            'custom_locations' => [['latitude' => -2.97, 'longitude' => 104.77, 'radius' => 10]],
            'ai_primary_text' => 'Copy B',
            'ai_headline' => 'Headline B',
        ]);

        (new PublishMetaAdJob($draftA->id))->handle(app(MetaMarketingService::class), app(TelegramBotService::class));
        (new PublishMetaAdJob($draftB->id))->handle(app(MetaMarketingService::class), app(TelegramBotService::class));

        $draftA->refresh();
        $draftB->refresh();

        $this->assertSame('camp_A', $draftA->meta_campaign_id);
        $this->assertSame('ad_A', $draftA->meta_ad_id);
        $this->assertSame(AdDraft::STATUS_PAUSED_ON_META, $draftA->status);

        $this->assertSame('camp_B', $draftB->meta_campaign_id);
        $this->assertSame('ad_B', $draftB->meta_ad_id);
        $this->assertSame(AdDraft::STATUS_PAUSED_ON_META, $draftB->status);
    }

    public function test_multi_ai_providers_support_and_automatic_fallback(): void
    {
        $primaryGroq = AiProvider::create([
            'name' => 'Groq Primary',
            'provider' => AiProvider::PROVIDER_GROQ,
            'api_key' => 'gsk_primary_fail',
            'model' => 'llama-3.3-70b-versatile',
            'is_default' => true,
            'is_active' => true,
            'priority' => 1,
        ]);

        $fallbackOpenAi = AiProvider::create([
            'name' => 'OpenAI Backup',
            'provider' => AiProvider::PROVIDER_OPENAI,
            'api_key' => 'sk_backup_success',
            'model' => 'gpt-4o-mini',
            'is_default' => false,
            'is_active' => true,
            'priority' => 2,
        ]);

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response(['error' => ['message' => 'Rate limit exceeded']], 429),
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'title' => 'Fallback OpenAI Berhasil',
                                'daily_budget' => 75000,
                                'age_min' => 22,
                                'age_max' => 40,
                                'genders' => [],
                                'destination_type' => 'WHATSAPP',
                                'destination_url' => '',
                                'objective' => 'OUTCOME_ENGAGEMENT',
                                'cta_type' => 'WHATSAPP_MESSAGE',
                                'radius_km' => 15,
                                'interest_keywords' => ['WiFi'],
                                'target_platforms' => ['META', 'TIKTOK'],
                                'ai_primary_text' => '🔥 Promo Fallback OpenAI!',
                                'ai_headline' => 'Pasang WiFi Sekarang',
                                'ai_description' => 'Gratis Instalasi',
                                'tiktok_ad_text' => '🔥 Pasang WiFi sekarang! #wifi',
                                'google_headline' => 'Pasang WiFi Cepat',
                                'google_long_headline' => 'Pasang WiFi Cepat Tanpa FUP',
                                'google_description' => 'Hubungi WA kami sekarang.',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(OpenRouterService::class);
        $result = $service->parseAndGenerateDraft(
            'Promo WiFi radius 15km budget 75rb ke WA',
            [['latitude' => -1.61, 'longitude' => 103.61]],
            Setting::current(),
            $primaryGroq->id
        );

        $this->assertSame('Fallback OpenAI Berhasil', $result['title']);
        $this->assertSame(75000, $result['daily_budget']);
        $this->assertSame(15, $result['radius_km']);
        $this->assertNotNull($fallbackOpenAi->id);
    }
}
