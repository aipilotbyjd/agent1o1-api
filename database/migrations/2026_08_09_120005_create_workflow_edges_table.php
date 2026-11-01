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
        Schema::create('workflow_edges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('from_node_id')->constrained('workflow_nodes')->cascadeOnDelete();
            $table->foreignUuid('to_node_id')->constrained('workflow_nodes')->cascadeOnDelete();
            $table->string('condition')->nullable();
            $table->timestamps();

            $table->unique(['from_node_id', 'to_node_id', 'condition']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_edges');
    }
};
