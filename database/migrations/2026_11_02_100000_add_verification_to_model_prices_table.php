<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a model price can be trusted, who confirmed it, and the higher
 * rates some models charge once a request's input passes a long-context
 * threshold (applied to the whole request). Prices copied from
 * `config/billing.php` were placeholders, so they start as `demo`; every
 * other existing price starts `unverified` until an operator confirms it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('model_prices', function (Blueprint $table) {
            $table->string('status')->default('unverified')->after('source');
            $table->timestamp('verified_at')->nullable()->after('status');
            $table->string('verified_by')->nullable()->after('verified_at');
            $table->unsignedInteger('long_context_threshold')->nullable()->after('cache_write_per_mtok');
            $table->decimal('long_input_per_mtok', 12, 6)->nullable()->after('long_context_threshold');
            $table->decimal('long_output_per_mtok', 12, 6)->nullable()->after('long_input_per_mtok');
        });

        DB::table('model_prices')->where('source', 'config')->update(['status' => 'demo']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_prices', function (Blueprint $table) {
            $table->dropColumn(['status', 'verified_at', 'verified_by', 'long_context_threshold', 'long_input_per_mtok', 'long_output_per_mtok']);
        });
    }
};
