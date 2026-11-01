<?php

use App\Models\Ai\ModelPrice;
use App\Models\Ai\ModelUsageDaily;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Ai\Operations\RollUpModelUsage;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    config(['billing.model_prices' => []]);

    $this->workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $this->run = Run::factory()->forWorkspace($this->workspace)->create();
});

/**
 * @param  array<string, mixed>  $usage
 */
function rolledNodeRun(array $usage, ?DateTimeInterface $at = null): NodeRun
{
    $nodeRun = NodeRun::factory()->forRun(test()->run)->create(['key' => 'n'.uniqid(), 'type' => 'ask_ai']);
    $nodeRun->forceFill(['usage' => $usage, 'created_at' => $at ?? now()])->save();

    return $nodeRun;
}

it('writes one row per workspace, day, provider and model', function () {
    ModelPrice::factory()->priced(10, 20)->create(['provider' => 'openai', 'model_id' => 'gpt-x']);
    rolledNodeRun(['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 1_000_000, 'completion_tokens' => 100_000]);
    rolledNodeRun(['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 1_000_000, 'completion_tokens' => 100_000]);
    rolledNodeRun(['provider' => 'openai', 'model' => 'gpt-y', 'prompt_tokens' => 5, 'completion_tokens' => 5]);

    $result = app(RollUpModelUsage::class)->run(1);

    expect($result)->toBe(['days' => 1, 'rows' => 2]);

    $row = ModelUsageDaily::query()->where('model', 'gpt-x')->sole();

    expect($row->workspace_id)->toBe($this->workspace->id)
        ->and($row->date->toDateString())->toBe(today()->toDateString())
        ->and($row)->calls->toBe(2)->prompt_tokens->toBe(2_000_000)->completion_tokens->toBe(200_000)
        // 2M in at $10 and 0.2M out at $20.
        ->and($row->estimated_cost_usd)->toBe(24.0)
        ->and(ModelUsageDaily::query()->where('model', 'gpt-y')->sole()->estimated_cost_usd)->toBeNull();
});

it('is safe to run twice: it rebuilds rather than adds', function () {
    rolledNodeRun(['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 10, 'completion_tokens' => 10]);

    app(RollUpModelUsage::class)->run(1);
    app(RollUpModelUsage::class)->run(1);

    expect(ModelUsageDaily::query()->count())->toBe(1)
        ->and(ModelUsageDaily::query()->sole()->calls)->toBe(1);
});

it('counts a call whose usage was written after the day it was created', function () {
    $nodeRun = rolledNodeRun(['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 1, 'completion_tokens' => 1], now()->subDay());

    app(RollUpModelUsage::class)->run(2);

    expect(ModelUsageDaily::query()->sole()->date->toDateString())->toBe(today()->subDay()->toDateString());

    // The usage grows later (a retry adds to it): the next rollup picks that up.
    $nodeRun->forceFill(['usage' => ['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 99, 'completion_tokens' => 1]])->save();
    app(RollUpModelUsage::class)->run(2);

    expect(ModelUsageDaily::query()->sole()->prompt_tokens)->toBe(99);
});

it('leaves days outside the window as they were', function () {
    rolledNodeRun(['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 1, 'completion_tokens' => 1], now()->subDays(10));
    app(RollUpModelUsage::class)->run(15);
    $old = ModelUsageDaily::query()->sole();

    NodeRun::query()->delete();
    app(RollUpModelUsage::class)->run(2);

    expect(ModelUsageDaily::query()->whereKey($old->id)->exists())->toBeTrue();
});

it('records a call on a workspace\'s own key as free', function () {
    rolledNodeRun(['provider' => 'byok-0f3a', 'model' => 'gpt-x', 'prompt_tokens' => 1_000_000, 'completion_tokens' => 1_000_000]);

    app(RollUpModelUsage::class)->run(1);

    expect(ModelUsageDaily::query()->sole())
        ->provider->toBe('own-key')
        ->own_key->toBeTrue()
        ->estimated_cost_usd->toBe(0.0);
});

it('re-costs a day when its price is set later and the day is rebuilt', function () {
    rolledNodeRun(['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 1_000_000, 'completion_tokens' => 0]);
    app(RollUpModelUsage::class)->run(1);
    expect(ModelUsageDaily::query()->sole()->estimated_cost_usd)->toBeNull();

    ModelPrice::factory()->priced(10, 0)->create(['provider' => 'openai', 'model_id' => 'gpt-x']);
    app(RollUpModelUsage::class)->run(1);

    expect(ModelUsageDaily::query()->sole()->estimated_cost_usd)->toBe(10.0);
});

it('ignores a record that names no model', function () {
    rolledNodeRun(['prompt_tokens' => 5]);

    expect(app(RollUpModelUsage::class)->run(1)['rows'])->toBe(0);
});

it('keeps its window within bounds and defaults from config', function () {
    config(['model_operations.rollup_days' => 3]);

    expect(app(RollUpModelUsage::class)->run()['days'])->toBe(3)
        ->and(app(RollUpModelUsage::class)->run(500)['days'])->toBe(RollUpModelUsage::MAX_DAYS)
        ->and(app(RollUpModelUsage::class)->run(0)['days'])->toBe(1);
});

it('runs from the command line', function () {
    rolledNodeRun(['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 1, 'completion_tokens' => 1]);

    $this->artisan('model-usage:rollup', ['--days' => 3])->expectsOutputToContain('Rebuilt 3 day(s): 1 row(s).')->assertSuccessful();
});
