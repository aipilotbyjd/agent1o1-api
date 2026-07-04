<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('execution_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('graph_snapshot')->nullable();
            $table->json('context_snapshot')->nullable();
            $table->json('output_buffer_snapshot')->nullable();
            $table->json('frontier_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_checkpoints');
    }
};
