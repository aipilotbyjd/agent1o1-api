<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Always-included agent knowledge now lives in the knowledge base: a
 * collection attached with `KnowledgeMode::Always`. Removes the old
 * per-agent table from databases that still have it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('agent_knowledge');
    }
};
