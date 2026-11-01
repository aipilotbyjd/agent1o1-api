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
        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_session_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->longText('content')->nullable();
            $table->json('tool_calls')->nullable();
            $table->string('tool_call_id')->nullable();
            $table->json('attachments')->nullable();
            $table->json('usage')->nullable();
            $table->uuid('compacted_into_id')->nullable();
            $table->json('tool_results')->nullable();
            $table->json('paused_state')->nullable();
            $table->timestamps();

            $table->index(['assistant_session_id', 'created_at']);
        });

        // Self-reference added once the primary key exists: Postgres
        // rejects it inside the same CREATE.
        Schema::table('assistant_messages', function (Blueprint $table) {
            $table->foreign('compacted_into_id')->references('id')->on('assistant_messages')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
    }
};
