<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Executable code snippets attached to a `Skill`. Storage only: no
 * execution wiring yet; running `code` requires a sandboxed executor that
 * does not exist in this codebase yet, see docs/AGENTS_PLAN.md.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('skill_scripts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('skill_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('language');
            $table->longText('code');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['skill_id', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skill_scripts');
    }
};
