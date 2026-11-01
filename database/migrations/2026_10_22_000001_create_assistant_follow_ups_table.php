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
        Schema::create('assistant_follow_ups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assistant_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('thread_id');
            $table->string('subject')->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('remind_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'remind_at']);
            $table->index(['assistant_id', 'thread_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_follow_ups');
    }
};
