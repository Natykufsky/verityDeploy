<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->timestamp('vhost_apply_last_run_at')->nullable()->after('live_configuration_last_error');
            $table->longText('vhost_apply_last_output')->nullable()->after('vhost_apply_last_run_at');
            $table->string('vhost_apply_last_error')->nullable()->after('vhost_apply_last_output');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn([
                'vhost_apply_last_run_at',
                'vhost_apply_last_output',
                'vhost_apply_last_error',
            ]);
        });
    }
};
