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
        Schema::create('trigger_presets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('category');
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('signature_scheme')->nullable();
            $table->string('dedupe_header')->nullable();
            $table->string('dedupe_payload_path')->nullable();
            $table->json('config')->nullable();
            $table->json('fields')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trigger_presets');
    }
};
