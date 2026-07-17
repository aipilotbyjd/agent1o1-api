<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            // Per-workspace star / favourite flag surfaced on the workflows list
            // (FAVORITES stat card + star toggle).
            $table->boolean('is_favorite')->default(false)->after('is_locked');

            $table->index(['workspace_id', 'is_favorite']);
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'is_favorite']);
            $table->dropColumn('is_favorite');
        });
    }
};
