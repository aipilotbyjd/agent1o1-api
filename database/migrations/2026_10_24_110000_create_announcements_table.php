<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app announcements: banners and scheduled maintenance notices, shown to
 * everyone or to a segment (see `BroadcastAudience`). Unpublished until a
 * super admin publishes; shown between `starts_at` and `ends_at`.
 * A dismissal is kept per member, so closing a banner never hides it from
 * anyone else.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->default('banner');
            $table->string('severity')->default('info');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url', 2048)->nullable();
            $table->boolean('dismissible')->default(true);
            $table->boolean('blocks_writes')->default(false);
            $table->json('audience')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['published_at', 'starts_at', 'ends_at']);
        });

        Schema::create('announcement_dismissals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('dismissed_at');

            $table->unique(['announcement_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_dismissals');
        Schema::dropIfExists('announcements');
    }
};
