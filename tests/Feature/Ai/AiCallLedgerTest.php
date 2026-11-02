<?php

use App\Ai\Agents\Judges\EvalJudgeAgent;
use App\Ai\Observability\AiCallContext;
use App\Models\Ai\AiCallEvent;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\ModelPrice;
use App\Models\User;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\AiManager;
use Laravel\Ai\Events\AgentFailedOver;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Events\StepFailed;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;

beforeEach(function () {
    config(['billing.model_prices' => [], 'ai.providers.anthropic.key' => 'platform-key']);

    $this->provider = app(AiManager::class)->textProvider('anthropic');
    $this->workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    ModelPrice::factory()->create(['provider' => 'anthropic', 'model_id' => 'claude-x', 'input_per_mtok' => 3, 'output_per_mtok' => 15, 'cache_read_per_mtok' => 0.3]);
});

function ledgerStep(object $provider, int $input, int $output, int $cacheReads = 0, int $step = 0): StepCompleted
{
    return new StepCompleted(
        'inv-1', $step, new EvalJudgeAgent, $provider, 'claude-x', true,
        new StepResponse('', [], FinishReason::Stop, new TextUsage($input, $output, $cacheReads), new Meta('anthropic', 'claude-x')),
        850,
    );
}

it('records every provider step with its tokens, latency and real cost, attributed to the work it belongs to', function () {
    app(AiCallContext::class)->run([
        'workspace_id' => $this->workspace->id,
        'feature' => 'agent_chat',
        'run_id' => '00000000-0000-0000-0000-000000000001',
        'subject_type' => 'run',
        'subject_id' => 'run-1',
    ], function () {
        event(ledgerStep($this->provider, 100_000, 10_000, 40_000));
        event(ledgerStep($this->provider, 50_000, 5_000, step: 1));
    });

    $events = AiCallEvent::query()->orderBy('step')->get();
    $price = ModelPrice::query()->sole();

    // Step 0: 60k uncached × $3 + 40k cached × $0.30 + 10k out × $15 = $0.18 + $0.012 + $0.15.
    expect($events)->toHaveCount(2)
        ->and($events[0])->toMatchArray([
            'workspace_id' => $this->workspace->id,
            'feature' => 'agent_chat',
            'kind' => 'text',
            'provider' => 'anthropic',
            'model' => 'claude-x',
            'status' => AiCallEvent::STATUS_OK,
            'latency_ms' => 850,
            'input_tokens' => 100_000,
            'cache_read_tokens' => 40_000,
            'output_tokens' => 10_000,
            'subject_type' => 'run',
            'price_status' => 'verified',
            'price_version_id' => $price->currentVersion()->id,
        ])
        ->and($events[0]->provider_cost_usd)->toEqualWithDelta(0.342, 0.000001)
        ->and($events[0]->trace_id)->not->toBeNull()
        ->and($events[1]->trace_id)->toBe($events[0]->trace_id)
        ->and(Context::get(AiCallContext::TRACE_KEY))->toBe($events[0]->trace_id);
});

it('names the feature after the agent when the caller did not', function () {
    event(ledgerStep($this->provider, 10, 10));

    expect(AiCallEvent::query()->sole()->feature)->toBe('judges');
});

it('learns who pays from the BYOK registrar, which every model call passes through', function () {
    app(ByokProviderRegistrar::class)->apply(['anthropic' => 'claude-x'], null, $this->workspace->id, null);

    event(ledgerStep($this->provider, 10, 10));

    expect(AiCallEvent::query()->sole()->workspace_id)->toBe($this->workspace->id);
});

it('records a failed step, and marks it failed over when the SDK moves on to the next provider', function () {
    $error = new ProviderConnectionException('down');

    event(new StepFailed('inv-9', 0, new EvalJudgeAgent, $this->provider, 'claude-x', false, $error, 30_000));

    expect(AiCallEvent::query()->sole())->toMatchArray([
        'status' => AiCallEvent::STATUS_FAILED,
        'error_class' => ProviderConnectionException::class,
        'error_message' => 'down',
        'provider_cost_usd' => 0.0,
    ]);

    event(new AgentFailedOver('inv-9', new EvalJudgeAgent, $this->provider, 'claude-x', $error));

    expect(AiCallEvent::query()->sole()->status)->toBe(AiCallEvent::STATUS_FAILED_OVER);
});

it('counts a call on the workspace\'s own key as free to the platform', function () {
    $credential = AiProviderCredential::factory()->create(['workspace_id' => $this->workspace->id, 'execution_provider' => 'anthropic']);
    [$chain] = app(ByokProviderRegistrar::class)->apply(['anthropic' => 'claude-x'], null, $this->workspace->id, null);
    $byok = app(AiManager::class)->textProvider(array_key_first($chain));

    event(ledgerStep($byok, 100_000, 10_000));

    expect(array_key_first($chain))->toBe('byok-'.$credential->id)
        ->and(AiCallEvent::query()->sole())->toMatchArray(['is_byok' => true, 'provider_cost_usd' => 0.0, 'price_status' => 'byok']);
});

it('never lets a bookkeeping failure break the call it describes', function () {
    Schema::drop('ai_call_events');

    expect(fn () => event(ledgerStep($this->provider, 10, 10)))->not->toThrow(Throwable::class);
});

it('prunes events past the retention window', function () {
    config(['model_operations.call_ledger.retention_days' => 30]);
    AiCallEvent::factory()->create(['occurred_at' => now()->subDays(31)]);
    AiCallEvent::factory()->create(['occurred_at' => now()->subDays(5)]);

    $this->artisan('ai-calls:prune')->assertSuccessful();

    expect(AiCallEvent::query()->count())->toBe(1);
});
