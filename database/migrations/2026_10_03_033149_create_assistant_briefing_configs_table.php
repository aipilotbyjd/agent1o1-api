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
        Schema::create('assistant_briefing_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->boolean('enabled')->default(false);
            $table->timestamp('paused_at')->nullable();
            $table->json('schedule')->nullable();
            $table->string('connector_scope')->default('all');
            $table->json('connector_keys')->nullable();
            $table->text('instructions')->nullable();
            $table->json('delivery')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['assistant_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_briefing_configs');
    }
};
