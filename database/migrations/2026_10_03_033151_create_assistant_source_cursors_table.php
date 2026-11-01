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
        Schema::create('assistant_source_cursors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_briefing_config_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->timestamp('cursor_at');
            $table->timestamps();

            $table->unique(['assistant_briefing_config_id', 'source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_source_cursors');
    }
};
