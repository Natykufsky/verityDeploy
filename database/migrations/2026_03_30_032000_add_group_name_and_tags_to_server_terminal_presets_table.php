<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_terminal_presets', function (Blueprint $table): void {
            if (! Schema::hasColumn('server_terminal_presets', 'group_name')) {
                $table->string('group_name')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('server_terminal_presets', 'tags')) {
                $table->json('tags')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('server_terminal_presets', function (Blueprint $table): void {
            if (Schema::hasColumn('server_terminal_presets', 'tags')) {
                $table->dropColumn('tags');
            }

            if (Schema::hasColumn('server_terminal_presets', 'group_name')) {
                $table->dropColumn('group_name');
            }
        });
    }
};
