<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->string('app_logo_path')->nullable()->after('app_name');
            $table->string('app_favicon_path')->nullable()->after('app_logo_path');
            $table->string('app_tagline')->nullable()->after('app_favicon_path');
            $table->text('app_description')->nullable()->after('app_tagline');
            $table->string('app_support_url')->nullable()->after('app_description');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'app_logo_path',
                'app_favicon_path',
                'app_tagline',
                'app_description',
                'app_support_url',
            ]);
        });
    }
};
