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
        Schema::create('provider_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider');

            // The provider's own id for the event: what makes a retried
            // delivery harmless.
            $table->string('event_id');
            $table->string('event_type');
            $table->string('routing_key')->nullable();
            $table->json('payload');
            $table->string('status')->default('received');
            $table->text('error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index(['provider', 'routing_key']);
            $table->index('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_events');
    }
};
