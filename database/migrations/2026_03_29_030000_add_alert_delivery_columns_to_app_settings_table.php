<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->boolean('alert_email_enabled')->default(false)->after('github_oauth_last_error');
            $table->boolean('alert_webhooks_enabled')->default(false)->after('alert_email_enabled');
            $table->text('alert_webhook_urls')->nullable()->after('alert_webhooks_enabled');
            $table->text('alert_webhook_secret')->nullable()->after('alert_webhook_urls');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'alert_email_enabled',
                'alert_webhooks_enabled',
                'alert_webhook_urls',
                'alert_webhook_secret',
            ]);
        });
    }
};
