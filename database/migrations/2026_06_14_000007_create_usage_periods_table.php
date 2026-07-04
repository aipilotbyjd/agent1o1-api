<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('credits_limit');
            $table->unsignedInteger('credits_from_packs')->default(0);
            $table->unsignedInteger('credits_rolled_over')->default(0);
            $table->unsignedInteger('credits_used')->default(0);
            $table->unsignedInteger('executions_total')->default(0);
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['workspace_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_periods');
    }
};
