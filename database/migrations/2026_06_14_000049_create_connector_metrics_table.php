<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connector_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('connector', 100);
            $table->date('date');
            $table->unsignedInteger('total_calls')->default(0);
            $table->unsignedInteger('success_calls')->default(0);
            $table->unsignedInteger('failed_calls')->default(0);
            $table->unsignedBigInteger('total_duration_ms')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'connector', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_metrics');
    }
};
