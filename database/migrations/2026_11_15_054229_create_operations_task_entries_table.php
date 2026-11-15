<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_task_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('operations_task_id')->constrained('operations_tasks')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('period', 20);
            $table->text('note')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->foreignUuid('actor_id')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['operations_task_id', 'period']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_task_entries');
    }
};
