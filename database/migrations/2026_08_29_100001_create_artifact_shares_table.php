<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit per-user grants on an artifact group, checked when the group's
 * `general_access` is `restricted`: see `Artifact::isAccessibleBy()`.
 * Keyed by `group_id` rather than `artifact_id` so a share applies to every
 * version, not just the one it was granted on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifact_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('group_id');
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifact_shares');
    }
};
