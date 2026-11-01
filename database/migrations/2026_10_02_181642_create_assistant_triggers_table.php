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
        Schema::create('assistant_triggers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->text('prompt');
            $table->string('cron')->nullable();
            $table->string('timezone')->default('UTC');
            $table->timestamp('run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->string('webhook_token', 64)->nullable()->unique();
            $table->string('status')->default('active');
            $table->string('created_by')->default('owner');
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_run_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_triggers');
    }
};
