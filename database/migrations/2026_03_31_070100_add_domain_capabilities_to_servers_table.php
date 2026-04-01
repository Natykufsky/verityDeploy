<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->boolean('can_manage_domains')->default(false)->after('provider_metadata');
            $table->boolean('can_manage_vhosts')->default(false)->after('can_manage_domains');
            $table->boolean('can_manage_dns')->default(false)->after('can_manage_vhosts');
            $table->boolean('can_manage_ssl')->default(false)->after('can_manage_dns');
        });

        DB::table('servers')
            ->where('connection_type', 'cpanel')
            ->update([
                'can_manage_domains' => true,
                'can_manage_vhosts' => false,
                'can_manage_dns' => true,
                'can_manage_ssl' => true,
            ]);

        DB::table('servers')
            ->where(function ($query): void {
                $query->whereNull('connection_type')->orWhere('connection_type', '!=', 'cpanel');
            })
            ->update([
                'can_manage_domains' => true,
                'can_manage_vhosts' => true,
                'can_manage_dns' => false,
                'can_manage_ssl' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropColumn([
                'can_manage_domains',
                'can_manage_vhosts',
                'can_manage_dns',
                'can_manage_ssl',
            ]);
        });
    }
};
