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
        Schema::create('assistant_meetings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_id')->constrained()->cascadeOnDelete();
            $table->string('provider_event_id');
            $table->string('title');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->json('attendees')->nullable();
            $table->boolean('is_external')->default(false);
            $table->string('html_link', 1000)->nullable();
            $table->text('description')->nullable();
            $table->string('prep_status')->default('none');
            $table->timestamps();

            $table->unique(['assistant_id', 'provider_event_id', 'starts_at']);
            $table->index(['assistant_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_meetings');
    }
};
