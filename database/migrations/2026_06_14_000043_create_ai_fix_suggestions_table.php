<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_fix_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('execution_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('node_id');
            $table->string('node_type');
            $table->text('diagnosis');
            $table->json('suggestions');
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['execution_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_fix_suggestions');
    }
};
