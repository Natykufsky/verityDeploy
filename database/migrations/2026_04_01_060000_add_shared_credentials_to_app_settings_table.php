<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->string('default_ssh_user')->nullable()->after('default_ssh_port');
            $table->text('default_ssh_key')->nullable()->after('default_ssh_user');
            $table->text('default_sudo_password')->nullable()->after('default_ssh_key');
            $table->string('default_cpanel_username')->nullable()->after('default_sudo_password');
            $table->text('default_cpanel_api_token')->nullable()->after('default_cpanel_username');
            $table->unsignedInteger('default_cpanel_api_port')->default(2083)->after('default_cpanel_api_token');
            $table->string('default_dns_provider')->default('manual')->after('default_cpanel_api_port');
            $table->string('default_dns_zone_id')->nullable()->after('default_dns_provider');
            $table->text('default_dns_api_token')->nullable()->after('default_dns_zone_id');
            $table->boolean('default_dns_proxy_records')->default(true)->after('default_dns_api_token');
            $table->text('default_webhook_secret')->nullable()->after('default_dns_proxy_records');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'default_ssh_user',
                'default_ssh_key',
                'default_sudo_password',
                'default_cpanel_username',
                'default_cpanel_api_token',
                'default_cpanel_api_port',
                'default_dns_provider',
                'default_dns_zone_id',
                'default_dns_api_token',
                'default_dns_proxy_records',
                'default_webhook_secret',
            ]);
        });
    }
};
