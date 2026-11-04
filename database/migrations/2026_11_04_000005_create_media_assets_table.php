<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('file_name');
            $table->string('file_path')->unique();
            $table->string('media_type')->default('VIDEO')->index(); // VIDEO or IMAGE
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->float('duration_seconds')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->json('extracted_frames')->nullable();
            $table->text('ai_visual_analysis')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('ad_drafts', function (Blueprint $table) {
            $table->foreignId('media_asset_id')->nullable()->after('video_local_path')->constrained('media_assets')->nullOnDelete();
            $table->string('media_type')->default('VIDEO')->after('media_asset_id');
            $table->text('ai_visual_context')->nullable()->after('media_type');
        });
    }

    public function down(): void
    {
        Schema::table('ad_drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_asset_id');
            $table->dropColumn(['media_type', 'ai_visual_context']);
        });

        Schema::dropIfExists('media_assets');
    }
};
