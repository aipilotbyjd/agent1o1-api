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
        Schema::create('assistant_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_session_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assistant_turn_id')->constrained()->cascadeOnDelete();
            $table->string('tool_call_id');
            $table->string('tool');
            $table->json('arguments')->nullable();
            $table->string('effect');
            $table->string('reason')->nullable();
            $table->string('status')->default('pending');
            $table->text('result')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['assistant_session_id', 'tool_call_id']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_actions');
    }
};
