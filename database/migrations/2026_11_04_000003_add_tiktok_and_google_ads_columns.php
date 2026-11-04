<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // TikTok Marketing API v1.3 Credentials
            $table->text('tiktok_access_token')->nullable();
            $table->string('tiktok_advertiser_id')->nullable();
            $table->string('tiktok_identity_id')->nullable();
            $table->string('tiktok_identity_type')->default('CUSTOMIZED_USER');
            $table->string('tiktok_pixel_id')->nullable();
            $table->string('tiktok_default_location_id')->default('1643084'); // Indonesia

            // Google Ads API v18 & YouTube Data API v3 OAuth2 Credentials
            $table->string('google_developer_token')->nullable();
            $table->string('google_client_id')->nullable();
            $table->text('google_client_secret')->nullable();
            $table->text('google_refresh_token')->nullable();
            $table->string('google_customer_id')->nullable();
            $table->string('google_login_customer_id')->nullable();
        });

        Schema::table('ad_drafts', function (Blueprint $table) {
            $table->json('target_platforms')->nullable();

            // TikTok Ads Columns
            $table->string('tiktok_ad_text', 150)->nullable();
            $table->json('tiktok_location_ids')->nullable();
            $table->string('tiktok_video_id')->nullable();
            $table->string('tiktok_image_id')->nullable();
            $table->string('tiktok_campaign_id')->nullable()->index();
            $table->string('tiktok_adgroup_id')->nullable();
            $table->string('tiktok_ad_id')->nullable();
            $table->string('tiktok_status')->default('DRAFT')->index();
            $table->longText('tiktok_error_log')->nullable();

            // Google Ads & YouTube Columns
            $table->string('google_headline', 60)->nullable();
            $table->string('google_long_headline', 120)->nullable();
            $table->string('google_description', 120)->nullable();
            $table->string('youtube_video_id')->nullable();
            $table->string('google_video_asset_resource')->nullable();
            $table->string('google_budget_resource')->nullable();
            $table->string('google_campaign_id')->nullable()->index();
            $table->string('google_adgroup_id')->nullable();
            $table->string('google_ad_id')->nullable();
            $table->string('google_status')->default('DRAFT')->index();
            $table->longText('google_error_log')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ad_drafts', function (Blueprint $table) {
            $table->dropColumn([
                'target_platforms',
                'tiktok_ad_text',
                'tiktok_location_ids',
                'tiktok_video_id',
                'tiktok_image_id',
                'tiktok_campaign_id',
                'tiktok_adgroup_id',
                'tiktok_ad_id',
                'tiktok_status',
                'tiktok_error_log',
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
            ]);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'tiktok_access_token',
                'tiktok_advertiser_id',
                'tiktok_identity_id',
                'tiktok_identity_type',
                'tiktok_pixel_id',
                'tiktok_default_location_id',
                'google_developer_token',
                'google_client_id',
                'google_client_secret',
                'google_refresh_token',
                'google_customer_id',
                'google_login_customer_id',
            ]);
        });
    }
};
