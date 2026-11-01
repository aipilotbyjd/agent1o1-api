<?php

use App\Models\Agents\AgentMessage;
use App\Models\Ai\ModelPrice;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Ai\Operations\ModelUsageReport;
use App\Services\Ai\Operations\RollUpModelUsage;
use App\Services\Workspaces\WorkspaceService;

/**
 * @param  array<string, mixed>  $usage
 */
function usedNodeRun(array $usage, ?DateTimeInterface $at = null, ?Run $run = null): NodeRun
{
    $nodeRun = NodeRun::factory()->forRun($run ?? test()->run)->create(['key' => 'n'.uniqid(), 'type' => 'ask_ai']);
    $nodeRun->forceFill(['usage' => $usage, 'created_at' => $at ?? now()])->save();

    return $nodeRun;
}

/**
 * Rolls up everything recorded so far and reads the report, as the hourly job would.
 *
 * @return array<string, mixed>
 */
function usageReport(int $days, ?string $workspaceId = null): array
{
    app(RollUpModelUsage::class)->run(RollUpModelUsage::MAX_DAYS);

    return app(ModelUsageReport::class)->report($days, $workspaceId);
}

beforeEach(function () {
    config(['billing.model_prices' => []]);

    $this->workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $this->run = Run::factory()->forWorkspace($this->workspace)->create();
});

it('adds up calls and tokens per model across both sources and prices them', function () {
    ModelPrice::factory()->priced(10, 20)->create(['provider' => 'openai', 'model_id' => 'gpt-x']);

    usedNodeRun(['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 1_000_000, 'completion_tokens' => 0]);
    usedNodeRun(['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 0, 'completion_tokens' => 500_000]);
    AgentMessage::factory()->assistant()->create(['usage' => ['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 1_000_000, 'completion_tokens' => 0]]);

    $report = usageReport(7);
    $row = $report['models'][0];

    expect($report['sources'])->toBe(['workflow node runs', 'agent chat messages'])
        ->and($report['refreshed_at'])->not->toBeNull()
        ->and($row)->toMatchArray(['provider' => 'openai', 'model' => 'gpt-x', 'calls' => 3, 'prompt_tokens' => 2_000_000, 'completion_tokens' => 500_000, 'priced' => true])
        // 2M in at $10 and 0.5M out at $20.
        ->and($row['estimated_cost_usd'])->toBe(30.0)
        ->and($report['totals'])->toMatchArray(['calls' => 3, 'estimated_cost_usd' => 30.0, 'unpriced_calls' => 0, 'own_key_calls' => 0]);
});

it('counts calls on a workspace\'s own key but charges you nothing for them', function () {
    usedNodeRun(['provider' => 'byok-0f3a', 'model' => 'gpt-x', 'prompt_tokens' => 1_000_000, 'completion_tokens' => 1_000_000]);
    usedNodeRun(['provider' => 'byok-77ab', 'model' => 'gpt-x', 'prompt_tokens' => 1, 'completion_tokens' => 1]);

    $report = usageReport(7);

    expect($report['models'])->toHaveCount(1)
        ->and($report['models'][0])->toMatchArray(['provider' => 'own key', 'own_key' => true, 'calls' => 2, 'estimated_cost_usd' => 0.0])
        ->and($report['totals'])->toMatchArray(['own_key_calls' => 2, 'estimated_cost_usd' => 0.0, 'unpriced_calls' => 0]);
});

it('reports calls to a model nobody priced without inventing a cost', function () {
    usedNodeRun(['provider' => 'openai', 'model' => 'mystery', 'prompt_tokens' => 10, 'completion_tokens' => 10]);

    $report = usageReport(7);

    expect($report['models'][0])->toMatchArray(['priced' => false, 'estimated_cost_usd' => null, 'unpriced_calls' => 1])
        ->and($report['totals'])->toMatchArray(['unpriced_calls' => 1, 'estimated_cost_usd' => 0.0]);
});

it('orders models by what they cost you', function () {
    ModelPrice::factory()->priced(1, 1)->create(['provider' => 'openai', 'model_id' => 'cheap']);
    ModelPrice::factory()->priced(100, 100)->create(['provider' => 'openai', 'model_id' => 'dear']);

    foreach (['cheap', 'cheap', 'cheap', 'dear'] as $model) {
        usedNodeRun(['provider' => 'openai', 'model' => $model, 'prompt_tokens' => 1_000_000, 'completion_tokens' => 0]);
    }

    expect(array_column(usageReport(7)['models'], 'model'))->toBe(['dear', 'cheap']);
});

it('only looks at the window asked for', function () {
    usedNodeRun(['provider' => 'openai', 'model' => 'recent', 'prompt_tokens' => 1, 'completion_tokens' => 1]);
    usedNodeRun(['provider' => 'openai', 'model' => 'old', 'prompt_tokens' => 1, 'completion_tokens' => 1], now()->subDays(20));

    expect(array_column(usageReport(7)['models'], 'model'))->toBe(['recent'])
        ->and(array_column(usageReport(30)['models'], 'model'))->toEqualCanonicalizing(['recent', 'old']);
});

it('keeps the window within bounds', function () {
    expect(app(ModelUsageReport::class)->report(500, null)['days'])->toBe(ModelUsageReport::MAX_DAYS)
        ->and(app(ModelUsageReport::class)->report(0, null)['days'])->toBe(1);
});

it('skips records that say nothing about a model', function () {
    usedNodeRun(['prompt_tokens' => 5]);

    expect(usageReport(7)['models'])->toBe([]);
});

it('narrows to one workspace', function () {
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Globex']);
    $otherRun = Run::factory()->forWorkspace($other)->create();

    usedNodeRun(['provider' => 'openai', 'model' => 'mine', 'prompt_tokens' => 1, 'completion_tokens' => 1]);
    usedNodeRun(['provider' => 'openai', 'model' => 'theirs', 'prompt_tokens' => 1, 'completion_tokens' => 1], run: $otherRun);

    expect(array_column(usageReport(7, $this->workspace->id)['models'], 'model'))->toBe(['mine'])
        ->and(array_column(usageReport(7, $other->id)['models'], 'model'))->toBe(['theirs']);
});

it('ranks the workspaces by what they cost you, and leaves out calls on their own key', function () {
    ModelPrice::factory()->priced(10, 0)->create(['provider' => 'openai', 'model_id' => 'gpt-x']);
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Globex']);
    $otherRun = Run::factory()->forWorkspace($other)->create();

    usedNodeRun(['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 1_000_000, 'completion_tokens' => 0]);
    usedNodeRun(['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 5_000_000, 'completion_tokens' => 0], run: $otherRun);
    usedNodeRun(['provider' => 'byok-1', 'model' => 'gpt-x', 'prompt_tokens' => 9_000_000, 'completion_tokens' => 0]);

    app(RollUpModelUsage::class)->run();
    $top = app(ModelUsageReport::class)->topWorkspaces(7);

    expect($top[0])->toMatchArray(['name' => 'Globex', 'estimated_cost_usd' => 50.0, 'calls' => 1])
        ->and($top[1])->toMatchArray(['name' => 'Acme', 'estimated_cost_usd' => 10.0, 'calls' => 1]);
});

it('gives a zero-filled daily trend', function () {
    ModelPrice::factory()->priced(10, 0)->create(['provider' => 'openai', 'model_id' => 'gpt-x']);

    usedNodeRun(['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 1_000_000, 'completion_tokens' => 0]);
    usedNodeRun(['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 2_000_000, 'completion_tokens' => 0], now()->subDays(2));

    app(RollUpModelUsage::class)->run(5);
    $daily = app(ModelUsageReport::class)->daily(4);

    expect(array_column($daily, 'calls'))->toBe([0, 1, 0, 1])
        ->and(array_column($daily, 'estimated_cost_usd'))->toBe([0.0, 20.0, 0.0, 10.0])
        ->and($daily[3]['date'])->toBe(today()->toDateString());
});
