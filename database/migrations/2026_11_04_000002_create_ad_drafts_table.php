<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_chat_id')->nullable()->index();
            $table->string('title');
            $table->text('raw_user_prompt');
            $table->string('video_local_path');
            $table->string('meta_video_id')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->string('objective')->default('OUTCOME_ENGAGEMENT');
            $table->string('destination_type')->default('WHATSAPP');
            $table->string('destination_url')->nullable();
            $table->bigInteger('daily_budget')->default(50000);
            $table->integer('age_min')->default(21);
            $table->integer('age_max')->default(50);
            $table->json('genders')->nullable();
            $table->json('custom_locations');
            $table->json('excluded_locations')->nullable();
            $table->json('interests')->nullable();
            $table->text('ai_primary_text');
            $table->string('ai_headline');
            $table->string('ai_description')->nullable();
            $table->string('cta_type')->default('WHATSAPP_MESSAGE');
            $table->string('meta_campaign_id')->nullable()->index();
            $table->string('meta_adset_id')->nullable();
            $table->string('meta_creative_id')->nullable();
            $table->string('meta_ad_id')->nullable();
            $table->string('status')->default('DRAFT')->index();
            $table->longText('error_log')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_drafts');
    }
};
