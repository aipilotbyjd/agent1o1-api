<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credits held for work in flight: an agent turn or an AI step that has
 * started but not been charged yet. The pre-flight check subtracts them from
 * the balance, so twenty runs started at once against an almost empty
 * balance cannot all pass a check that only one of them should. A hold is
 * released when its work is charged, and expires on its own if a worker dies.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('subject_type', 64);
            $table->string('subject_id', 64);
            $table->unsignedInteger('credits');
            $table->timestamp('expires_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'released_at', 'expires_at']);
            $table->unique(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_reservations');
    }
};
