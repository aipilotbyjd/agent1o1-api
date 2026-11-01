<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic membership row for a `TemplateCollection` "pack" - `templatable`
 * resolves to either a `WorkflowTemplate` or an `AgentTemplate` via the
 * `workflow_template`/`agent_template` morph map aliases (registered in
 * `AppServiceProvider::configureMorphMap()`), so one pack can mix both kinds.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('template_collection_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('collection_id')->constrained('template_collections')->cascadeOnDelete();
            $table->string('templatable_type');
            $table->uuid('templatable_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['collection_id', 'templatable_type', 'templatable_id'], 'template_collection_items_unique');
            $table->index(['templatable_type', 'templatable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_collection_items');
    }
};
