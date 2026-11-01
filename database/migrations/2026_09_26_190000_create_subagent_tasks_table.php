<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subagent_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('parent_session_id')->constrained('agent_sessions')->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('session_id')->nullable()->constrained('agent_sessions')->nullOnDelete();
            $table->text('task');
            $table->string('status')->default('queued');
            $table->longText('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();

            $table->index(['parent_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subagent_tasks');
    }
};
