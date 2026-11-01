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
        Schema::create('folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('type')->default('workflow');
            $table->index(['workspace_id', 'type', 'parent_id']);
            $table->timestamps();

            $table->index(['workspace_id', 'parent_id']);
        });

        Schema::table('folders', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('folders')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
