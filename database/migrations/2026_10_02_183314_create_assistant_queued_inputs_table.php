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
        Schema::create('assistant_queued_inputs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_session_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['assistant_session_id', 'consumed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_queued_inputs');
    }
};
