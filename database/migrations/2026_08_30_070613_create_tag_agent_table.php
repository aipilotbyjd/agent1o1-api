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
        Schema::create('tag_agent', function (Blueprint $table) {
            $table->foreignUuid('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->primary(['tag_id', 'agent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tag_agent');
    }
};
