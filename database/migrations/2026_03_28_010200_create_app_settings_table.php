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
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->default('verityDeploy');
            $table->string('default_branch')->default('main');
            $table->string('default_web_root')->default('public');
            $table->string('default_php_version')->nullable();
            $table->string('default_deploy_source')->default('git');
            $table->unsignedInteger('default_ssh_port')->default(22);
            $table->text('github_api_token')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
