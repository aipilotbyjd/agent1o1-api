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
        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('folder_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->string('provider')->default('anthropic');
            $table->string('model')->nullable();
            $table->foreignUuid('model_catalog_id')->nullable()->constrained('model_catalog')->nullOnDelete();
            $table->decimal('temperature', 3, 2)->nullable();
            $table->json('settings')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('icon', 50)->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('allow_self_updates')->default(false);
            $table->boolean('allow_skill_editing')->default(false);
            $table->boolean('allow_self_clone')->default(true);
            $table->string('autonomy_mode')->default('ask');
            $table->boolean('test_mode')->default(false);
            $table->boolean('allow_web_fetch')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
