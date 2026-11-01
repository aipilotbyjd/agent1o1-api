<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a model costs us per million tokens, by the backend that serves it.
 * Keyed by (provider, model id) rather than by route: the same backend model
 * can serve several catalog entries, and it has one price. `CreditMeter`
 * turns a call's tokens into dollars with this, and the dollars into credits.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('model_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider');
            $table->string('model_id');
            $table->decimal('input_per_mtok', 12, 6);
            $table->decimal('output_per_mtok', 12, 6);
            $table->string('source')->default('estimate');
            $table->timestamp('checked_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_prices');
    }
};
