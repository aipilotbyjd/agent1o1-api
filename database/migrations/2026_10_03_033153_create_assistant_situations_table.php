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
        Schema::create('assistant_situations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assistant_briefing_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('next_step')->nullable();
            $table->json('sources')->nullable();
            $table->string('status')->default('open');
            $table->foreignUuid('assistant_session_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['assistant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_situations');
    }
};
