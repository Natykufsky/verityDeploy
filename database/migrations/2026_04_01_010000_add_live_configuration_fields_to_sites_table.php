<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->json('live_configuration_snapshot')->nullable()->after('github_webhook_last_error');
            $table->timestamp('live_configuration_synced_at')->nullable()->after('live_configuration_snapshot');
            $table->text('live_configuration_last_error')->nullable()->after('live_configuration_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn([
                'live_configuration_snapshot',
                'live_configuration_synced_at',
                'live_configuration_last_error',
            ]);
        });
    }
};
