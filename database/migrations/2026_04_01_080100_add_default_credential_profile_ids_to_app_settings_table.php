<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->foreignId('default_ssh_credential_profile_id')->nullable()->after('default_ssh_port')->constrained('credential_profiles')->nullOnDelete();
            $table->foreignId('default_cpanel_credential_profile_id')->nullable()->after('default_cpanel_api_port')->constrained('credential_profiles')->nullOnDelete();
            $table->foreignId('default_github_credential_profile_id')->nullable()->after('github_oauth_last_error')->constrained('credential_profiles')->nullOnDelete();
            $table->foreignId('default_dns_credential_profile_id')->nullable()->after('default_dns_proxy_records')->constrained('credential_profiles')->nullOnDelete();
            $table->foreignId('default_webhook_credential_profile_id')->nullable()->after('default_webhook_secret')->constrained('credential_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_ssh_credential_profile_id');
            $table->dropConstrainedForeignId('default_cpanel_credential_profile_id');
            $table->dropConstrainedForeignId('default_github_credential_profile_id');
            $table->dropConstrainedForeignId('default_dns_credential_profile_id');
            $table->dropConstrainedForeignId('default_webhook_credential_profile_id');
        });
    }
};
