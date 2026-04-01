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
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'ssh_port',
                'ssh_user',
                'ssh_key',
                'sudo_password',
                'cpanel_username',
                'cpanel_api_token',
                'cpanel_api_port',
                'dns_api_token',
                'dns_zone_id',
                'dns_proxy_records',
                'dns_provider',
                'host',
                'port',
                'username',
                'private_key',
                'passphrase',
            ]);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'webhook_secret',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedSmallInteger('ssh_port')->default(22)->after('ip_address');
            $table->string('ssh_user')->nullable()->after('ssh_port');
            $table->text('ssh_key')->nullable()->after('connection_type');
            $table->text('sudo_password')->nullable()->after('ssh_key');
            $table->string('cpanel_username')->nullable();
            $table->text('cpanel_api_token')->nullable();
            $table->unsignedSmallInteger('cpanel_api_port')->default(2083);
            $table->text('dns_api_token')->nullable();
            $table->string('dns_zone_id')->nullable();
            $table->boolean('dns_proxy_records')->default(true);
            $table->string('dns_provider')->default('manual');
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->default(22);
            $table->string('username')->nullable();
            $table->text('private_key')->nullable();
            $table->text('passphrase')->nullable();
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->text('webhook_secret')->nullable();
        });
    }
};
