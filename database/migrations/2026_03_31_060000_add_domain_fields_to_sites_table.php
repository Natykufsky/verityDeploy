<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            if (! Schema::hasColumn('sites', 'primary_domain')) {
                $table->string('primary_domain')->nullable()->after('deploy_path');
            }

            if (! Schema::hasColumn('sites', 'subdomains')) {
                $table->json('subdomains')->nullable()->after('primary_domain');
            }

            if (! Schema::hasColumn('sites', 'alias_domains')) {
                $table->json('alias_domains')->nullable()->after('subdomains');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            if (Schema::hasColumn('sites', 'alias_domains')) {
                $table->dropColumn('alias_domains');
            }

            if (Schema::hasColumn('sites', 'subdomains')) {
                $table->dropColumn('subdomains');
            }

            if (Schema::hasColumn('sites', 'primary_domain')) {
                $table->dropColumn('primary_domain');
            }
        });
    }
};
