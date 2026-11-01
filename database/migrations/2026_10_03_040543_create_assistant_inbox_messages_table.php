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
        Schema::create('assistant_inbox_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_inbox_config_id')->constrained()->cascadeOnDelete();
            $table->string('provider_message_id');
            $table->string('thread_id')->nullable();
            $table->string('from')->nullable();
            $table->string('subject')->nullable();
            $table->text('snippet')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('status');
            $table->json('labels')->nullable();
            $table->boolean('archived')->default(false);
            $table->string('skipped_reason')->nullable();
            $table->text('suggestion')->nullable();
            $table->string('draft_provider_id')->nullable();
            $table->string('draft_hash')->nullable();
            $table->string('draft_status')->nullable();
            $table->json('usage')->nullable();
            $table->timestamps();

            $table->unique(['assistant_inbox_config_id', 'provider_message_id']);
            $table->index(['assistant_inbox_config_id', 'received_at']);
            $table->index(['assistant_inbox_config_id', 'thread_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_inbox_messages');
    }
};
