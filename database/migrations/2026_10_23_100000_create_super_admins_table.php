<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Super Admin accounts: a separate identity from `users`, provisioned
 * only from the command line or by an existing owner. There is no signup,
 * no email verification and no social login.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('super_admins', function (Blueprint $table) {
            // Declared before `created_by` references this table: PostgreSQL needs the key to exist first.
            $table->uuid('id');
            $table->primary('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('viewer')->index();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->unsignedInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admins');
    }
};
