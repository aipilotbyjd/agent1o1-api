<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_embeddings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('collection')->default('default');
            $table->string('source')->nullable();
            $table->text('chunk_text');
            $table->json('embedding');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'collection']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_embeddings');
    }
};
