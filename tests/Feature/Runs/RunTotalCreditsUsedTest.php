<?php

use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use Illuminate\Support\Facades\DB;

function queriesToTotalCredits(int $nodeCount): int
{
    $workflow = Workflow::factory()->create();
    $run = Run::factory()->forWorkflow($workflow)->create();
    NodeRun::factory()->count($nodeCount)->forRun($run)->create();

    $run = $run->fresh()->load('nodeRuns');

    DB::flushQueryLog();
    DB::enableQueryLog();
    $run->totalCreditsUsed();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

it('reads the credits of every loaded node run in one query however many nodes the run has', function () {
    expect(queriesToTotalCredits(2))->toBe(1)
        ->and(queriesToTotalCredits(6))->toBe(1);
});
