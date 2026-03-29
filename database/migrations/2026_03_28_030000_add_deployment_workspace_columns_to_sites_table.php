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
        Schema::table('sites', function (Blueprint $table): void {
            $table->json('environment_variables')->nullable()->after('webhook_secret');
            $table->json('shared_files')->nullable()->after('environment_variables');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn([
                'environment_variables',
                'shared_files',
            ]);
        });
    }
};
