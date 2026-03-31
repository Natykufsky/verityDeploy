<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployment_steps', function (Blueprint $table): void {
            if (! Schema::hasColumn('deployment_steps', 'error_message')) {
                $table->text('error_message')->nullable()->after('output');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deployment_steps', function (Blueprint $table): void {
            if (Schema::hasColumn('deployment_steps', 'error_message')) {
                $table->dropColumn('error_message');
            }
        });
    }
};
