<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Model usage by day, workspace and model: the table the usage report, the
 * top-spender list and the spend guard read, so none of them scans the usage
 * of every node run and chat message. Rebuilt for the latest days by
 * `model-usage:rollup`. A call served on a workspace's own key is recorded
 * under the provider `own-key` and costs nothing; a model with no price has no
 * cost recorded.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('model_usage_daily', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date');
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->boolean('own_key')->default(false);
            $table->unsignedInteger('calls')->default(0);
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('completion_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 14, 6)->nullable();
            $table->timestamps();

            $table->unique(['date', 'workspace_id', 'provider', 'model']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_usage_daily');
    }
};
