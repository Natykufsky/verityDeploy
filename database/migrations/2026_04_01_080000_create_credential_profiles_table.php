<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type', 32)->index();
            $table->text('description')->nullable();
            $table->text('settings')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_profiles');
    }
};
