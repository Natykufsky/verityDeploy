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
        Schema::table('sites', function (Blueprint $table) {
            $table->string('environment')->default('production');
            $table->boolean('create_database')->default(false);
            $table->string('database_name')->nullable();
            $table->string('project_type')->default('php');
            $table->string('build_command')->nullable();
            $table->string('start_command')->nullable();
            $table->integer('port')->nullable();
            $table->boolean('auto_ssl')->default(true);
            $table->boolean('deploy_after_create')->default(true);
            // Rename local_source_path to local_source_archive if exists, else add
            if (Schema::hasColumn('sites', 'local_source_path')) {
                $table->renameColumn('local_source_path', 'local_source_archive');
            } else {
                $table->string('local_source_archive')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['environment', 'create_database', 'database_name', 'project_type', 'build_command', 'start_command', 'port', 'auto_ssl', 'deploy_after_create']);
            // Rename back if needed
            if (Schema::hasColumn('sites', 'local_source_archive')) {
                $table->renameColumn('local_source_archive', 'local_source_path');
            }
        });
    }
};
