<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per code run: the single source for usage, cost against credits
 * charged, a workspace's daily cap and the monthly budget. Holds no code
 * and no output.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sandbox_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40);
            $table->string('source', 20);
            $table->string('mode', 20);
            $table->string('language', 20);
            $table->string('status', 20);
            $table->unsignedInteger('seconds')->default(0);
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['provider', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sandbox_runs');
    }
};
