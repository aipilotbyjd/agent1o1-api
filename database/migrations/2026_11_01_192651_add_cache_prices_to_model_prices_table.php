<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a model charges per million cached input tokens: read back from the
 * provider's prompt cache, and written into it. Laravel AI 1.x reports both
 * counts; a price left empty falls back to the input price scaled by
 * `config('billing.cache_price_fallback')`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('model_prices', function (Blueprint $table) {
            $table->decimal('cache_read_per_mtok', 12, 6)->nullable()->after('output_per_mtok');
            $table->decimal('cache_write_per_mtok', 12, 6)->nullable()->after('cache_read_per_mtok');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_prices', function (Blueprint $table) {
            $table->dropColumn(['cache_read_per_mtok', 'cache_write_per_mtok']);
        });
    }
};
