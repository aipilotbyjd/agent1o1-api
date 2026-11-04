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
        Schema::table('agent_messages', function (Blueprint $table) {
            $table->text('reasoning')->nullable()->after('stop_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_messages', function (Blueprint $table) {
            $table->dropColumn('reasoning');
        });
    }
};
