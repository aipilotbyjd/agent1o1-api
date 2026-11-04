<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A plan's level (Free 1, Starter 2, Pro 3, ...), the measure models
     * limited to a plan compare against: see `Plan::reaches()`. Existing
     * plans are numbered by monthly credits, the measure used until now, so
     * nothing a workspace can use changes on deploy.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('tier')->nullable()->after('credits_monthly');
        });

        $tier = 0;
        $previousCredits = null;

        foreach (DB::table('plans')->orderBy('credits_monthly')->get(['id', 'credits_monthly']) as $plan) {
            if ($plan->credits_monthly !== $previousCredits) {
                $tier++;
                $previousCredits = $plan->credits_monthly;
            }

            DB::table('plans')->where('id', $plan->id)->update(['tier' => $tier]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};
