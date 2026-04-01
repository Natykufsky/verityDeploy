<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->string('dns_provider')->default('manual')->after('can_manage_ssl');
            $table->string('dns_zone_id')->nullable()->after('dns_provider');
            $table->text('dns_api_token')->nullable()->after('dns_zone_id');
            $table->boolean('dns_proxy_records')->default(true)->after('dns_api_token');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropColumn([
                'dns_provider',
                'dns_zone_id',
                'dns_api_token',
                'dns_proxy_records',
            ]);
        });
    }
};
