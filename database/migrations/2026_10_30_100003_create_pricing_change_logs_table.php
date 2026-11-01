<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A readable before/after history of the pricing catalog, kept apart
     * from the hash-chained audit log (which still gets its own entry).
     */
    public function up(): void
    {
        Schema::create('pricing_change_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_type', 20);
            $table->string('subject_key')->nullable();
            $table->uuid('super_admin_id')->nullable();
            $table->string('action', 60);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['plan_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_change_logs');
    }
};
