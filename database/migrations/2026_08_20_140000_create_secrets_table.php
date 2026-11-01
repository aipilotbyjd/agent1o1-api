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
        Schema::create('secrets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('key');
            $table->string('description')->nullable();
            $table->text('value');
            $table->boolean('is_secret')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // No soft deletes, unlike `connector_credentials`: deleting a
            // secret is a revocation, and a soft-deleted row would both keep
            // the ciphertext around and block the key from being re-created.
            $table->unique(['workspace_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('secrets');
    }
};
