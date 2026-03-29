<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->text('recovery_hint')->nullable()->after('error_message');
        });

        Schema::table('cpanel_wizard_runs', function (Blueprint $table): void {
            $table->text('recovery_hint')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('cpanel_wizard_runs', function (Blueprint $table): void {
            $table->dropColumn('recovery_hint');
        });

        Schema::table('deployments', function (Blueprint $table): void {
            $table->dropColumn('recovery_hint');
        });
    }
};
