<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('alert_inbox_enabled')->default(true)->after('password');
            $table->boolean('alert_email_enabled')->default(true)->after('alert_inbox_enabled');
            $table->string('alert_minimum_level')->default('warning')->after('alert_email_enabled');
        });

        Schema::create('operational_alert_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('target')->nullable();
            $table->string('title');
            $table->string('level')->default('warning');
            $table->string('status')->default('queued');
            $table->unsignedInteger('response_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_alert_deliveries');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'alert_inbox_enabled',
                'alert_email_enabled',
                'alert_minimum_level',
            ]);
        });
    }
};
