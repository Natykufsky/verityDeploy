<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('running');
            $table->longText('output')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamp('tested_at')->nullable()->index();
            $table->integer('exit_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_health_checks');
    }
};
