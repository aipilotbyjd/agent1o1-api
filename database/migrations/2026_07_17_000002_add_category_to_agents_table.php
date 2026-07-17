<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Optional grouping surfaced on the Agents list (category filter chips
            // + per-card tag). Free-form string; UI groups known values.
            $table->string('category')->nullable()->after('is_active');

            $table->index(['workspace_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'category']);
            $table->dropColumn('category');
        });
    }
};
