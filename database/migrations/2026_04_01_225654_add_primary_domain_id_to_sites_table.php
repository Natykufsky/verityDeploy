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
        Schema::table('sites', function (Blueprint $table) {
            $table->foreignId('primary_domain_id')
                ->nullable()
                ->after('server_id')
                ->constrained('domains')
                ->nullOnDelete();

            $table->dropColumn(['primary_domain', 'subdomains', 'alias_domains']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropForeign(['primary_domain_id']);
            $table->dropColumn('primary_domain_id');

            $table->string('primary_domain')->nullable();
            $table->json('subdomains')->nullable();
            $table->json('alias_domains')->nullable();
        });
    }
};
