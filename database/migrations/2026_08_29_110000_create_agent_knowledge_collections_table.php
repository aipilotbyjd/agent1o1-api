<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which `document_embeddings.collection`s an agent may search: Gumloop's
 * "attach a Brain source to an agent" (docs/gumloop/output/raw/core-concepts/brain.md
 * §"Giving an agent knowledge"). `collection` is a plain string, not a
 * foreign key: `document_embeddings.collection` has no model of its own,
 * it is just a grouping column: see that table's migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_knowledge_collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->string('collection');
            $table->string('mode')->default('search');
            $table->timestamps();

            $table->unique(['agent_id', 'collection']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_knowledge_collections');
    }
};
