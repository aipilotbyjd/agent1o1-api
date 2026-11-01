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
        Schema::create('template_collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Null workspace_id = a global/system pack visible to every workspace.
            $table->foreignUuid('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 7)->nullable();
            $table->string('visibility')->default('private');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'slug']);
            $table->index(['visibility', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_collections');
    }
};
