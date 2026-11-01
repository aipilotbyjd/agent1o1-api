<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel AI 1.x reports inclusive token counts (input with cache reads and
 * writes, output with reasoning) and breaks out the cached and reasoning
 * parts. The daily rollup keeps all five. Existing rows are carried over
 * from `prompt_tokens` / `completion_tokens`, which the next
 * `model-usage:rollup` of those days replaces with exact counts.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('model_usage_daily', function (Blueprint $table) {
            $table->unsignedBigInteger('input_tokens')->default(0)->after('calls');
            $table->unsignedBigInteger('output_tokens')->default(0)->after('input_tokens');
            $table->unsignedBigInteger('cache_read_tokens')->default(0)->after('output_tokens');
            $table->unsignedBigInteger('cache_write_tokens')->default(0)->after('cache_read_tokens');
            $table->unsignedBigInteger('reasoning_tokens')->default(0)->after('cache_write_tokens');
        });

        DB::table('model_usage_daily')->update([
            'input_tokens' => DB::raw('prompt_tokens'),
            'output_tokens' => DB::raw('completion_tokens'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_usage_daily', function (Blueprint $table) {
            $table->dropColumn(['input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_write_tokens', 'reasoning_tokens']);
        });
    }
};
