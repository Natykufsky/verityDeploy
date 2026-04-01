<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->foreignId('ssh_credential_profile_id')->nullable()->after('ssh_key')->constrained('credential_profiles')->nullOnDelete();
            $table->foreignId('cpanel_credential_profile_id')->nullable()->after('cpanel_api_token')->constrained('credential_profiles')->nullOnDelete();
            $table->foreignId('dns_credential_profile_id')->nullable()->after('dns_api_token')->constrained('credential_profiles')->nullOnDelete();
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->foreignId('github_credential_profile_id')->nullable()->after('github_webhook_last_error')->constrained('credential_profiles')->nullOnDelete();
            $table->foreignId('dns_credential_profile_id')->nullable()->after('live_configuration_last_error')->constrained('credential_profiles')->nullOnDelete();
            $table->foreignId('webhook_credential_profile_id')->nullable()->after('webhook_secret')->constrained('credential_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ssh_credential_profile_id');
            $table->dropConstrainedForeignId('cpanel_credential_profile_id');
            $table->dropConstrainedForeignId('dns_credential_profile_id');
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('github_credential_profile_id');
            $table->dropConstrainedForeignId('dns_credential_profile_id');
            $table->dropConstrainedForeignId('webhook_credential_profile_id');
        });
    }
};
