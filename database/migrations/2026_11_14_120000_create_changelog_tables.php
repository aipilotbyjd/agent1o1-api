<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The product changelog: release notes written by super admins and read by
 * members (or by anyone, for entries marked public). An entry is invisible
 * until it is published; a scheduled one is published by
 * `changelog:publish-due` once its time comes. Read state is kept per member,
 * so opening an entry never marks it read for anyone else.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('changelog_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('version', 40)->nullable();
            $table->string('title', 160);
            $table->string('summary', 500);
            $table->longText('body');
            $table->string('cover_image_url', 2048)->nullable();
            $table->string('category')->default('feature');
            $table->json('areas')->nullable();
            $table->string('visibility')->default('public');
            $table->json('audience')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_major')->default(false);
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['status', 'scheduled_for']);
        });

        Schema::create('changelog_reads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('changelog_entry_id')->constrained('changelog_entries')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->unique(['changelog_entry_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('changelog_reads');
        Schema::dropIfExists('changelog_entries');
    }
};
