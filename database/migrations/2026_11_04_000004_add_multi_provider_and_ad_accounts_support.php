<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Multi-Account Ads API Table (Supports Multiple Accounts per Provider: META, TIKTOK, GOOGLE)
        Schema::create('ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Meta BM 1 - Cabang Jambi", "TikTok Shop Utama"
            $table->string('provider')->index(); // META, TIKTOK, GOOGLE
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();

            // Meta Marketing API v21.0 Credentials
            $table->text('meta_access_token')->nullable();
            $table->string('meta_ad_account_id')->nullable();
            $table->string('meta_page_id')->nullable();
            $table->string('meta_whatsapp_number')->nullable();
            $table->string('meta_pixel_id')->nullable();

            // TikTok Marketing API v1.3 Credentials
            $table->text('tiktok_access_token')->nullable();
            $table->string('tiktok_advertiser_id')->nullable();
            $table->string('tiktok_identity_id')->nullable();
            $table->string('tiktok_identity_type')->default('CUSTOMIZED_USER');
            $table->string('tiktok_pixel_id')->nullable();
            $table->string('tiktok_default_location_id')->default('1643084');

            // Google Ads API v18 & YouTube Data API v3 Credentials
            $table->string('google_developer_token')->nullable();
            $table->string('google_client_id')->nullable();
            $table->text('google_client_secret')->nullable();
            $table->text('google_refresh_token')->nullable();
            $table->string('google_customer_id')->nullable();
            $table->string('google_login_customer_id')->nullable();

            // Connection Status Tracking
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->text('last_test_message')->nullable();

            $table->timestamps();
        });

        // 2. Multi-Provider AI LLM API Table (OpenRouter, OpenAI, Gemini, Groq, DeepSeek, Custom)
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "OpenRouter Utama", "Groq Llama 3.3 Backup", "OpenAI Direct"
            $table->string('provider')->default('OPENROUTER')->index(); // OPENROUTER, OPENAI, GEMINI, GROQ, DEEPSEEK, CUSTOM
            $table->string('base_url')->nullable();
            $table->text('api_key')->nullable();
            $table->string('model')->default('openai/gpt-4o-mini');
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('priority')->default(10);
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });

        // 3. Add Multi-AI Provider columns to settings table for quick default configuration
        Schema::table('settings', function (Blueprint $table) {
            $table->string('ai_provider')->default('OPENROUTER')->after('google_login_customer_id');
            $table->string('ai_base_url')->nullable()->after('ai_provider');
        });

        // 4. Link ad_drafts to specific AdAccount per platform (plus selected AI Provider)
        Schema::table('ad_drafts', function (Blueprint $table) {
            $table->foreignId('meta_account_id')->nullable()->after('target_platforms')->constrained('ad_accounts')->nullOnDelete();
            $table->foreignId('tiktok_account_id')->nullable()->after('meta_account_id')->constrained('ad_accounts')->nullOnDelete();
            $table->foreignId('google_account_id')->nullable()->after('tiktok_account_id')->constrained('ad_accounts')->nullOnDelete();
            $table->foreignId('ai_provider_id')->nullable()->after('google_account_id')->constrained('ai_providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ad_drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meta_account_id');
            $table->dropConstrainedForeignId('tiktok_account_id');
            $table->dropConstrainedForeignId('google_account_id');
            $table->dropConstrainedForeignId('ai_provider_id');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['ai_provider', 'ai_base_url']);
        });

        Schema::dropIfExists('ai_providers');
        Schema::dropIfExists('ad_accounts');
    }
};
