<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('databases', function (Blueprint $table) {
            if (! Schema::hasColumn('databases', 'password')) {
                $table->text('password')->nullable()->after('username');
            }
        });
    }

    public function down(): void
    {
        Schema::table('databases', function (Blueprint $table) {
            if (Schema::hasColumn('databases', 'password')) {
                $table->dropColumn('password');
            }
        });
    }
};
