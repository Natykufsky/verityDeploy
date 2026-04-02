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
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->enum('type', ['primary', 'addon', 'alias', 'subdomain'])->default('primary');
            $table->string('php_version')->nullable();
            $table->string('web_root')->nullable();
            $table->boolean('is_ssl_enabled')->default(false);
            $table->string('ssl_status')->nullable(); // pending, issued, expired, failed
            $table->dateTime('ssl_expires_at')->nullable();
            $table->text('ssl_certificate')->nullable();
            $table->text('ssl_key')->nullable();
            $table->text('ssl_chain')->nullable();
            $table->string('external_id')->nullable(); // For cPanel or DNS provider reference
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
