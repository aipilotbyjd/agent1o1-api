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
        Schema::create('assistant_inbox_labels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_inbox_config_id')->constrained()->cascadeOnDelete();
            $table->string('key')->nullable();
            $table->string('name');
            $table->text('definition');
            $table->string('color')->nullable();
            $table->string('group')->default('keep');
            $table->boolean('enabled')->default(true);
            $table->string('provider_label_id')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['assistant_inbox_config_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_inbox_labels');
    }
};
