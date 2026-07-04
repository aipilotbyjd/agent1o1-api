<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trigger_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->enum('category_type', ['manual', 'schedule', 'webhook', 'polling', 'app_specific']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category_type');
        });

        Schema::create('trigger_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('trigger_categories')->cascadeOnDelete();
            $table->string('slug', 100)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('execution_mode', ['manual', 'webhook', 'polling']);
            $table->enum('zapier_mode', ['instant', 'polling'])->nullable();
            $table->boolean('requires_credential')->default(false);
            $table->boolean('requires_config_fields')->default(false);
            $table->json('webhook_events')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category_id');
            $table->index('execution_mode');
        });

        Schema::create('trigger_type_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trigger_type_id')->constrained('trigger_types')->cascadeOnDelete();
            $table->string('field_name', 100);
            $table->string('field_label', 100);
            $table->enum('field_type', ['text', 'number', 'select', 'multiselect', 'date', 'time', 'cron', 'textarea']);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_secret')->default(false);
            $table->string('placeholder', 255)->nullable();
            $table->text('help_text')->nullable();
            $table->string('validation_regex', 500)->nullable();
            $table->json('options')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['trigger_type_id', 'field_name']);
            $table->index('trigger_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trigger_type_fields');
        Schema::dropIfExists('trigger_types');
        Schema::dropIfExists('trigger_categories');
    }
};
