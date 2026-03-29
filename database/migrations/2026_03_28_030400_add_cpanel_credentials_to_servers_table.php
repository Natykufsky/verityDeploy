<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->text('cpanel_api_token')->nullable()->after('sudo_password');
            $table->unsignedInteger('cpanel_api_port')->nullable()->default(2083)->after('cpanel_api_token');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropColumn(['cpanel_api_token', 'cpanel_api_port']);
        });
    }
};
