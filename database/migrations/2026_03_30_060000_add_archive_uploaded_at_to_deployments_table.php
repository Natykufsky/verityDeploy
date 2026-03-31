<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            if (! Schema::hasColumn('deployments', 'archive_uploaded_at')) {
                $table->timestamp('archive_uploaded_at')->nullable()->after('recovery_hint');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            if (Schema::hasColumn('deployments', 'archive_uploaded_at')) {
                $table->dropColumn('archive_uploaded_at');
            }
        });
    }
};
