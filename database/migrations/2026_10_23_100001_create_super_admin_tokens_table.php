<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super Admin sessions. Deliberately not Passport: these opaque tokens belong
 * to the `super_admins` table alone, so a user token can never validate here
 * and a Super Admin token can never validate against the user API.
 *
 * Only SHA-256 hashes are stored. Every refresh rotates both tokens inside
 * the same `family_id`; presenting an already-rotated refresh token revokes
 * the whole family, which is how a stolen refresh token gets noticed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('super_admin_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('super_admin_id')->constrained('super_admins')->cascadeOnDelete();
            $table->uuid('family_id')->index();
            $table->string('scope', 16);
            $table->string('access_token_hash', 64)->unique();
            $table->string('refresh_token_hash', 64)->nullable()->unique();
            $table->timestamp('access_expires_at');
            $table->timestamp('refresh_expires_at')->nullable();
            $table->timestamp('absolute_expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['super_admin_id', 'revoked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admin_tokens');
    }
};
