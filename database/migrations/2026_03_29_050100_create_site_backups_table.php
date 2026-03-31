<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_backups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_backup_id')->nullable()->constrained('site_backups')->nullOnDelete();
            $table->string('operation')->default('backup');
            $table->string('status')->default('pending');
            $table->string('label')->nullable();
            $table->string('source_release_path')->nullable();
            $table->string('snapshot_path')->nullable();
            $table->string('restored_release_path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum')->nullable();
            $table->text('output')->nullable();
            $table->text('error_message')->nullable();
            $table->text('recovery_hint')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_backups');
    }
};
