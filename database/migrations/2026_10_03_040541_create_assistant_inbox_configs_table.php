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
        Schema::create('assistant_inbox_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('gmail');
            $table->foreignUuid('connector_credential_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('draft_mode')->default('confident');
            $table->text('drafting_instructions')->nullable();
            $table->boolean('known_senders_only')->default(true);
            $table->boolean('skip_existing_labels')->default(false);
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_inbox_configs');
    }
};
