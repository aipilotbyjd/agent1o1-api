<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sign-in history of the Super Admin accounts: successes, failures,
 * lockouts and blocked addresses. It feeds the new-address and brute-force
 * alerts, and is what an owner reads when asking "who tried to get in?".
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('super_admin_login_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('super_admin_id')->nullable()->constrained('super_admins')->cascadeOnDelete();
            $table->string('email_attempted')->nullable();
            $table->string('event', 48);
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['super_admin_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admin_login_events');
    }
};
