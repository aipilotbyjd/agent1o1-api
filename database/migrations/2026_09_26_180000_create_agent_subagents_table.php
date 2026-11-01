<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_subagents', function (Blueprint $table) {
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subagent_id')->constrained('agents')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['agent_id', 'subagent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_subagents');
    }
};
