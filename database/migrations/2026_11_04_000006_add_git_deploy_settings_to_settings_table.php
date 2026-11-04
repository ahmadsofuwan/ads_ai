<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('deploy_webhook_secret')->nullable()->after('default_longitude');
            $table->string('deploy_branch')->default('main')->after('deploy_webhook_secret');
            $table->timestamp('last_deployed_at')->nullable()->after('deploy_branch');
            $table->string('last_deploy_commit')->nullable()->after('last_deployed_at');
            $table->string('last_deploy_status')->nullable()->after('last_deploy_commit');
            $table->text('last_deploy_log')->nullable()->after('last_deploy_status');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'deploy_webhook_secret',
                'deploy_branch',
                'last_deployed_at',
                'last_deploy_commit',
                'last_deploy_status',
                'last_deploy_log',
            ]);
        });
    }
};
