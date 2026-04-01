<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_terminal_runs', function (Blueprint $table): void {
            $table->foreignId('server_terminal_session_id')
                ->nullable()
                ->after('server_id')
                ->constrained('server_terminal_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('server_terminal_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('server_terminal_session_id');
        });
    }
};
