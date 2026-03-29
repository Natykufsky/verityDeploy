<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('github_webhook_path')->default('/webhooks/github')->after('default_ssh_port');
            $table->string('github_webhook_events')->default('push')->after('github_webhook_path');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'github_webhook_path',
                'github_webhook_events',
            ]);
        });
    }
};
