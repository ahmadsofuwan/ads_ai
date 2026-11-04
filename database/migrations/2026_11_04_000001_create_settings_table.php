<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->text('meta_access_token')->nullable();
            $table->string('meta_ad_account_id')->nullable();
            $table->string('meta_page_id')->nullable();
            $table->string('meta_whatsapp_number')->nullable();
            $table->string('meta_pixel_id')->nullable();
            $table->text('openrouter_api_key')->nullable();
            $table->string('openrouter_model')->default('openai/gpt-4o-mini');
            $table->string('telegram_bot_token')->nullable();
            $table->json('allowed_telegram_chat_ids')->nullable();
            $table->decimal('default_latitude', 10, 6)->default(-1.610123);
            $table->decimal('default_longitude', 10, 6)->default(103.613120);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
