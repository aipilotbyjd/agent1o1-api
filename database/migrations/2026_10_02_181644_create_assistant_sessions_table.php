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
        Schema::create('assistant_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assistant_trigger_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('status')->default('active');
            $table->string('origin')->default('web');
            $table->string('external_thread_ref')->nullable();
            $table->boolean('incognito')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->json('channel_context')->nullable();
            $table->timestamps();

            $table->index(['assistant_id', 'last_activity_at']);
            $table->unique(['assistant_id', 'origin', 'external_thread_ref']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_sessions');
    }
};
