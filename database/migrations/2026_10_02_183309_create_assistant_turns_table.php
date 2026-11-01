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
        Schema::create('assistant_turns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_session_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_message_id')->nullable()->constrained('assistant_messages')->nullOnDelete();
            $table->foreignUuid('assistant_message_id')->nullable()->constrained('assistant_messages')->nullOnDelete();
            $table->string('status')->default('queued');
            $table->text('error')->nullable();
            $table->json('usage')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['assistant_session_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_turns');
    }
};
