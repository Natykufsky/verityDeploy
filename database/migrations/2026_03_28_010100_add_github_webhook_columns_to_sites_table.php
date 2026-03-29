<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('github_webhook_id')->nullable()->after('webhook_secret');
            $table->string('github_webhook_status')->default('unprovisioned')->after('github_webhook_id');
            $table->timestamp('github_webhook_synced_at')->nullable()->after('github_webhook_status');
            $table->text('github_webhook_last_error')->nullable()->after('github_webhook_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'github_webhook_id',
                'github_webhook_status',
                'github_webhook_synced_at',
                'github_webhook_last_error',
            ]);
        });
    }
};
