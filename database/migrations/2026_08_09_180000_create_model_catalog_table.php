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
        Schema::create('model_catalog', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('display_name');
            $table->string('brand');
            $table->json('capabilities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_internal')->default(false);
            $table->string('description', 500)->nullable();
            $table->json('strengths')->nullable();
            $table->string('speed')->nullable();
            $table->unsignedTinyInteger('cost_tier')->nullable();
            $table->timestamp('retires_at')->nullable();
            $table->string('replacement_slug')->nullable();
            // The cheapest plan that may use this model; null means every plan. Compared by monthly credits, like `Workspace::currentPlan()`.
            $table->foreignUuid('min_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_catalog');
    }
};
