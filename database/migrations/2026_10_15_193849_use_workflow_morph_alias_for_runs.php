<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('runs')
            ->where('runnable_type', 'App\Models\Workflows\Workflow')
            ->update(['runnable_type' => 'workflow']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('runs')
            ->where('runnable_type', 'workflow')
            ->update(['runnable_type' => 'App\Models\Workflows\Workflow']);
    }
};
