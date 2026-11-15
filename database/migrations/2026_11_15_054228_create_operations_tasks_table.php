<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 80)->nullable()->unique();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('cadence', 20);
            $table->string('kind', 20);
            $table->string('check', 80)->nullable();
            $table->string('target', 80)->nullable();
            $table->unsignedSmallInteger('due_after_hours');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->boolean('is_system')->default(false);
            $table->foreignUuid('assigned_to')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['enabled', 'cadence', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_tasks');
    }
};
