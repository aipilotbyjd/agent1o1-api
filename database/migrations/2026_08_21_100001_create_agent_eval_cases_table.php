<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One prompt plus what should be true of the answer. `assertions` is a list
 * of `{type, value}` objects (`EvalAssertionType`): several per case, all of
 * which must hold for the case to pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_eval_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_eval_suite_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('input');
            $table->json('assertions');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['agent_eval_suite_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_eval_cases');
    }
};
