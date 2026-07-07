<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('execution_nodes', function (Blueprint $table) {
            // Number of times the node handler was invoked (1 = ran once, no retry).
            // Lets operators see transient-failure retries and supports idempotent
            // retry accounting.
            $table->unsignedInteger('attempt')->default(1)->after('duration_ms');
        });
    }

    public function down(): void
    {
        Schema::table('execution_nodes', function (Blueprint $table) {
            $table->dropColumn('attempt');
        });
    }
};
