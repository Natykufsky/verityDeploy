<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->string('provider_type')->default('manual')->after('ssh_user');
            $table->string('provider_reference')->nullable()->after('provider_type');
            $table->string('provider_region')->nullable()->after('provider_reference');
            $table->json('provider_metadata')->nullable()->after('provider_region');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropColumn([
                'provider_type',
                'provider_reference',
                'provider_region',
                'provider_metadata',
            ]);
        });
    }
};
