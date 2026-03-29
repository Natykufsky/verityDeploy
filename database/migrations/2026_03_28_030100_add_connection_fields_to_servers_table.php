<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->string('ip_address')->nullable()->after('name');
            $table->unsignedSmallInteger('ssh_port')->default(22)->after('ip_address');
            $table->string('ssh_user')->nullable()->after('ssh_port');
            $table->string('connection_type')->default('ssh_key')->after('ssh_user');
            $table->text('ssh_key')->nullable()->after('connection_type');
            $table->text('sudo_password')->nullable()->after('ssh_key');
            $table->json('metrics')->nullable()->after('sudo_password');
        });

        DB::statement("
            UPDATE servers
            SET
                ip_address = COALESCE(ip_address, host),
                ssh_port = COALESCE(ssh_port, port, 22),
                ssh_user = COALESCE(ssh_user, username),
                connection_type = CASE
                    WHEN connection_type IS NOT NULL THEN connection_type
                    WHEN host IN ('localhost', '127.0.0.1') THEN 'local'
                    ELSE 'ssh_key'
                END,
                ssh_key = COALESCE(ssh_key, private_key),
                sudo_password = COALESCE(sudo_password, passphrase),
                metrics = COALESCE(metrics, '{\"cpu_usage\":null,\"ram_usage\":null,\"disk_free\":null,\"uptime\":null}')
        ");

        DB::statement("
            UPDATE servers
            SET status = CASE
                WHEN status = 'connected' THEN 'online'
                WHEN status = 'failed' THEN 'error'
                ELSE 'offline'
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropColumn([
                'ip_address',
                'ssh_port',
                'ssh_user',
                'connection_type',
                'ssh_key',
                'sudo_password',
                'metrics',
            ]);
        });
    }
};
