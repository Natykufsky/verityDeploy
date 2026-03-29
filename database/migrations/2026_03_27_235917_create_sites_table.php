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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('repository_url')->nullable();
            $table->string('default_branch')->default('main');
            $table->string('deploy_path');
            $table->string('php_version', 20)->nullable();
            $table->string('web_root')->default('public');
            $table->string('deploy_source')->default('git');
            $table->text('webhook_secret')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_deployed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
