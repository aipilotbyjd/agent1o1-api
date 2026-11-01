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
        Schema::create('assistant_sandboxes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assistant_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_sandbox_id');
            $table->text('access_token')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('seconds_used')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_sandboxes');
    }
};
