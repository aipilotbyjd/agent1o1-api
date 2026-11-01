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
        Schema::create('assistant_style_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_style_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('body')->nullable();
            $table->string('source');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['assistant_style_profile_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_style_revisions');
    }
};
