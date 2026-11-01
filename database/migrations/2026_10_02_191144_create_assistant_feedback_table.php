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
        Schema::create('assistant_feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assistant_message_id')->constrained()->cascadeOnDelete();
            $table->string('rating');
            $table->text('comment')->nullable();
            $table->string('status')->default('pending');
            $table->json('applied_change')->nullable();
            $table->timestamps();

            $table->unique('assistant_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_feedback');
    }
};
