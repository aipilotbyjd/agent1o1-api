<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('execution_id')->constrained()->cascadeOnDelete();
            $table->string('node_id');
            $table->string('node_run_key');
            $table->string('node_type');
            $table->string('node_name');
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('input_data')->nullable();
            $table->json('output_data')->nullable();
            $table->json('error')->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->unsignedInteger('loop_index')->nullable();
            $table->string('parent_frame')->nullable();
            $table->timestamps();

            $table->unique(['execution_id', 'node_run_key']);
            $table->index(['execution_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_nodes');
    }
};
