<?php

use App\Models\Intelligence\Experiment;
use App\Models\Intelligence\ExperimentAssignment;
use App\Services\Intelligence\Experiments;
use Illuminate\Support\Facades\DB;

function trialExperiment(): Experiment
{
    return app(Experiments::class)->create([
        'key' => 'trial-credits',
        'name' => 'Trial credits',
        'variants' => [
            ['key' => 'control', 'weight' => 50, 'settings' => ['credits' => 2000]],
            ['key' => 'generous', 'weight' => 50, 'settings' => ['credits' => 4000]],
        ],
        'guard_threshold' => -1.0,
    ]);
}

it('returns the default until the experiment runs, then keeps each workspace on one variant', function () {
    $experiment = trialExperiment();
    $experiments = app(Experiments::class);
    $workspace = '00000000-0000-0000-0000-000000000001';

    expect($experiments->setting('trial-credits', $workspace, 'credits', 1000))->toBe(1000);

    $experiments->start($experiment);
    $first = $experiments->variant('trial-credits', $workspace);

    expect($first)->toBeIn(['control', 'generous'])
        ->and($experiments->variant('trial-credits', $workspace))->toBe($first)
        ->and($experiments->setting('trial-credits', $workspace, 'credits', 1000))->toBe($first === 'control' ? 2000 : 4000)
        ->and(ExperimentAssignment::query()->count())->toBe(1);
});

it('splits workspaces roughly by weight', function () {
    $experiments = app(Experiments::class);
    $experiments->start(trialExperiment());

    $variants = collect(range(1, 400))->map(fn (int $i): ?string => $experiments->variant('trial-credits', sprintf('00000000-0000-0000-0000-%012d', $i)))->countBy();

    expect($variants['control'])->toBeGreaterThan(150)->toBeLessThan(250);
});

it('stops an experiment whose variant falls under its guard', function () {
    $experiment = trialExperiment();
    $experiments = app(Experiments::class);
    $experiments->start($experiment);

    foreach (range(1, 30) as $i) {
        $workspace = sprintf('00000000-0000-0000-0000-%012d', $i);
        ExperimentAssignment::query()->create(['experiment_id' => $experiment->id, 'workspace_id' => $workspace, 'variant' => 'generous', 'assigned_at' => now()]);
        DB::table('fact_workspace_daily')->insert([
            'date' => today()->toDateString(), 'workspace_id' => $workspace, 'plan_slug' => 'pro', 'is_paying' => false,
            'credits_used' => 100, 'revenue_usd' => 0, 'provider_cost_usd' => 5, 'margin_usd' => -5, 'ai_calls' => 1, 'failed_calls' => 0,
        ]);
    }

    expect($experiments->guard())->toBe(['trial-credits'])
        ->and($experiment->fresh()->status)->toBe('stopped');
});
