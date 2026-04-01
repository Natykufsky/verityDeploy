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
        Schema::table('app_settings', function (Blueprint $table) {
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
                'github_api_token',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('default_ssh_user')->nullable();
            $table->text('default_ssh_key')->nullable();
            $table->string('default_sudo_password')->nullable();
            $table->string('default_cpanel_username')->nullable();
            $table->text('default_cpanel_api_token')->nullable();
            $table->integer('default_cpanel_api_port')->nullable()->default(2083);
            $table->string('default_dns_provider')->nullable()->default('manual');
            $table->string('default_dns_zone_id')->nullable();
            $table->text('default_dns_api_token')->nullable();
            $table->boolean('default_dns_proxy_records')->default(true);
            $table->text('default_webhook_secret')->nullable();
            $table->text('github_api_token')->nullable();
        });
    }
};
