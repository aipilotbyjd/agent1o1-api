<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Every price a backend model has had, and when it applied. A row closes
 * (`effective_to`) when the next one starts, so any past charge can be
 * re-priced exactly: each AI call event records the version it was billed
 * at. Kept after the price itself is deleted.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('model_price_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('model_price_id')->nullable()->index();
            $table->string('provider');
            $table->string('model_id');
            $table->unsignedInteger('version');
            $table->decimal('input_per_mtok', 12, 6);
            $table->decimal('output_per_mtok', 12, 6);
            $table->decimal('cache_read_per_mtok', 12, 6)->nullable();
            $table->decimal('cache_write_per_mtok', 12, 6)->nullable();
            $table->unsignedInteger('long_context_threshold')->nullable();
            $table->decimal('long_input_per_mtok', 12, 6)->nullable();
            $table->decimal('long_output_per_mtok', 12, 6)->nullable();
            $table->string('status');
            $table->string('source');
            $table->string('changed_by')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();

            $table->index(['provider', 'model_id', 'effective_from']);
        });

        $now = now();

        foreach (DB::table('model_prices')->get() as $price) {
            DB::table('model_price_versions')->insert([
                'id' => (string) Str::uuid(),
                'model_price_id' => $price->id,
                'provider' => $price->provider,
                'model_id' => $price->model_id,
                'version' => 1,
                'input_per_mtok' => $price->input_per_mtok,
                'output_per_mtok' => $price->output_per_mtok,
                'cache_read_per_mtok' => $price->cache_read_per_mtok,
                'cache_write_per_mtok' => $price->cache_write_per_mtok,
                'status' => $price->status,
                'source' => $price->source,
                'effective_from' => $price->created_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_price_versions');
    }
};
