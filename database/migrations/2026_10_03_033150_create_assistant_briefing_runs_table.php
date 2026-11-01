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
        Schema::create('assistant_briefing_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_briefing_config_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assistant_meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->string('run_key');
            $table->string('status')->default('queued');
            $table->string('trigger')->default('schedule');
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->text('summary')->nullable();
            $table->longText('document')->nullable();
            $table->json('source_results')->nullable();
            $table->json('delivery_results')->nullable();
            $table->json('usage')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['assistant_briefing_config_id', 'run_key']);
            $table->index(['assistant_briefing_config_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_briefing_runs');
    }
};
