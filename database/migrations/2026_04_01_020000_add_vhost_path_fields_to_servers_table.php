<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->string('vhost_config_path')->nullable()->after('can_manage_ssl');
            $table->string('vhost_enabled_path')->nullable()->after('vhost_config_path');
            $table->string('vhost_reload_command')->nullable()->after('vhost_enabled_path');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropColumn([
                'vhost_config_path',
                'vhost_enabled_path',
                'vhost_reload_command',
            ]);
        });
    }
};
