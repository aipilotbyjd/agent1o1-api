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
        Schema::create('workflow_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('type');
            $table->json('config')->nullable();
            $table->json('position')->nullable();
            $table->json('pinned_data')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->foreignUuid('pinned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workflow_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_nodes');
    }
};
