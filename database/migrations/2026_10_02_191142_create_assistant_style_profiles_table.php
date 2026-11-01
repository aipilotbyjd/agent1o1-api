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
        Schema::create('assistant_style_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->text('body')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->unique(['assistant_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_style_profiles');
    }
};
