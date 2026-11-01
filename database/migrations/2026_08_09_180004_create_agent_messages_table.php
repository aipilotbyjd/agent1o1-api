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
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_session_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->longText('content');
            $table->foreignUuid('skill_id')->nullable()->constrained()->nullOnDelete();
            $table->json('tool_calls')->nullable();
            $table->string('tool_call_id')->nullable();
            $table->json('usage')->nullable();
            $table->json('tool_results')->nullable();
            $table->json('paused_state')->nullable();
            $table->timestamps();

            $table->index(['agent_session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};
