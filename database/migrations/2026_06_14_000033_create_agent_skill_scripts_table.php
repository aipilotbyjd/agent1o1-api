<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_skill_scripts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('skill_id')->constrained('agent_skills')->cascadeOnDelete();
            $table->string('name');
            $table->text('description');
            $table->string('language', 20)->default('php');
            $table->longText('code');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index('skill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_skill_scripts');
    }
};
