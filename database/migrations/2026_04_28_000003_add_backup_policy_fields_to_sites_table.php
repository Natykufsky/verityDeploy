<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->boolean('backup_enabled')->default(false)->after('deploy_after_create');
            $table->string('backup_schedule')->default('daily')->after('backup_enabled');
            $table->unsignedSmallInteger('backup_retention_count')->default(5)->after('backup_schedule');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn([
                'backup_enabled',
                'backup_schedule',
                'backup_retention_count',
            ]);
        });
    }
};
