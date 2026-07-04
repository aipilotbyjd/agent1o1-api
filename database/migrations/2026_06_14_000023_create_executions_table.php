<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('wait_token', 64)->nullable()->index();
            $table->string('mode')->default('manual');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('trigger_data')->nullable();
            $table->json('result_data')->nullable();
            $table->json('error')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->unsignedInteger('retry_delay_seconds')->default(0);
            $table->uuid('parent_execution_id')->nullable();
            $table->unsignedBigInteger('credits_consumed')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['workflow_id', 'status']);
            $table->index(['workspace_id', 'created_at']);
        });

        Schema::table('executions', function (Blueprint $table) {
            $table->foreign('parent_execution_id')->references('id')->on('executions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executions');
    }
};
