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
        Schema::create('assistant_situation_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_situation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('body');
            $table->string('status')->default('todo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_situation_steps');
    }
};
