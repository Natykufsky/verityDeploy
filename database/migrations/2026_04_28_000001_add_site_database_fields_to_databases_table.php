<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('databases', function (Blueprint $table) {
            if (! Schema::hasColumn('databases', 'site_id')) {
                $table->foreignId('site_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('databases', 'server_id')) {
                $table->foreignId('server_id')->nullable()->after('site_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('databases', 'name')) {
                $table->string('name')->nullable()->after('server_id');
            }

            if (! Schema::hasColumn('databases', 'username')) {
                $table->string('username')->nullable()->after('name');
            }

            if (! Schema::hasColumn('databases', 'status')) {
                $table->string('status')->default('requested')->after('username');
            }

            if (! Schema::hasColumn('databases', 'provisioned_at')) {
                $table->timestamp('provisioned_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('databases', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('provisioned_at');
            }

            if (! Schema::hasColumn('databases', 'last_error')) {
                $table->text('last_error')->nullable()->after('last_synced_at');
            }

            if (! Schema::hasColumn('databases', 'notes')) {
                $table->text('notes')->nullable()->after('last_error');
            }
        });

    }

    public function down(): void
    {
        //
    }
};
