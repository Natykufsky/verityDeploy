<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->timestamp('ssl_last_synced_at')->nullable()->after('ssl_state');
            $table->text('ssl_last_error')->nullable()->after('ssl_last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn(['ssl_last_synced_at', 'ssl_last_error']);
        });
    }
};
