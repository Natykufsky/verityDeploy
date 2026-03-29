<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->string('github_oauth_client_id')->nullable()->after('github_api_token');
            $table->text('github_oauth_client_secret')->nullable()->after('github_oauth_client_id');
            $table->text('github_oauth_access_token')->nullable()->after('github_oauth_client_secret');
            $table->timestamp('github_oauth_connected_at')->nullable()->after('github_oauth_access_token');
            $table->text('github_oauth_last_error')->nullable()->after('github_oauth_connected_at');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'github_oauth_client_id',
                'github_oauth_client_secret',
                'github_oauth_access_token',
                'github_oauth_connected_at',
                'github_oauth_last_error',
            ]);
        });
    }
};
