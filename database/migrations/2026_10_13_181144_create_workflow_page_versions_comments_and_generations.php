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
        Schema::create('workflow_page_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_page_id')->constrained('workflow_pages')->cascadeOnDelete();
            $table->unsignedInteger('number');
            // published | saved | before_restore | before_generation
            $table->string('kind', 32);
            $table->string('label', 120)->nullable();
            $table->json('content');
            $table->json('variant_content')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['workflow_page_id', 'number']);
        });

        Schema::create('workflow_page_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_page_id')->constrained('workflow_pages')->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            // The block a thread is about; null for the page as a whole.
            $table->string('block_id', 64)->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workflow_page_id', 'parent_id', 'created_at']);
        });

        // A reply goes with its thread. Added once the table and its key exist.
        Schema::table('workflow_page_comments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('workflow_page_comments')->cascadeOnDelete();
        });

        Schema::create('workflow_page_generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_page_id')->constrained('workflow_pages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('prompt');
            $table->char('variant', 1)->default('a');
            $table->boolean('keep_theme')->default(true);
            // pending | running | completed | failed
            $table->string('status', 16)->default('pending');
            $table->json('content')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['workflow_page_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_page_generations');
        Schema::dropIfExists('workflow_page_comments');
        Schema::dropIfExists('workflow_page_versions');
    }
};
