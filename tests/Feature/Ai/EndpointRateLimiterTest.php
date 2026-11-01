<?php

use App\Ai\Agents\AdHocPromptAgent;
use App\Exceptions\ModelBusyException;
use App\Models\Ai\AiEndpoint;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\EndpointRateLimiter;
use App\Services\Ai\Operations\ModelHealthReport;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Passport\Passport;

/**
 * @param  array<string, mixed>  $attributes
 */
function limitedEndpoint(array $attributes = []): AiEndpoint
{
    return AiEndpoint::factory()->create(['slug' => 'my-vllm', ...$attributes]);
}

function newWorkspace(string $name = 'Acme'): Workspace
{
    return app(WorkspaceService::class)->create(User::factory()->create(), ['name' => $name]);
}

beforeEach(function () {
    config(['ai.providers.openai.key' => 'platform-key']);

    $this->workspace = newWorkspace();
    $this->other = newWorkspace('Globex');
});

it('lets everything through an endpoint with no limits, and through providers that cannot have them', function () {
    limitedEndpoint();

    foreach (range(1, 50) as $i) {
        expect(app(EndpointRateLimiter::class)->attempt('my-vllm', $this->workspace->id))->toBeNull();
    }

    expect(app(EndpointRateLimiter::class)->attempt('openai', $this->workspace->id))->toBeNull()
        ->and(app(EndpointRateLimiter::class)->attempt('no-such-provider', $this->workspace->id))->toBeNull();
});

it('stops one workspace at its limit without touching another', function () {
    limitedEndpoint(['workspace_requests_per_minute' => 3]);
    $limiter = app(EndpointRateLimiter::class);

    foreach (range(1, 3) as $i) {
        expect($limiter->attempt('my-vllm', $this->workspace->id))->toBeNull();
    }

    $wait = $limiter->attempt('my-vllm', $this->workspace->id);

    expect($wait)->toBeInt()->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(60)
        ->and($limiter->attempt('my-vllm', $this->other->id))->toBeNull();
});

it('lets the workspace back in when the minute is up', function () {
    limitedEndpoint(['workspace_requests_per_minute' => 1]);
    $limiter = app(EndpointRateLimiter::class);

    $limiter->attempt('my-vllm', $this->workspace->id);
    expect($limiter->attempt('my-vllm', $this->workspace->id))->not->toBeNull();

    $this->travel(61)->seconds();

    expect($limiter->attempt('my-vllm', $this->workspace->id))->toBeNull();
});

it('caps all workspaces together with the total limit', function () {
    limitedEndpoint(['total_requests_per_minute' => 2]);
    $limiter = app(EndpointRateLimiter::class);

    expect($limiter->attempt('my-vllm', $this->workspace->id))->toBeNull()
        ->and($limiter->attempt('my-vllm', $this->other->id))->toBeNull()
        ->and($limiter->attempt('my-vllm', $this->workspace->id))->not->toBeNull()
        ->and($limiter->attempt('my-vllm', newWorkspace('Initech')->id))->not->toBeNull();
});

it('does not charge a workspace for a call the total turned away', function () {
    limitedEndpoint(['workspace_requests_per_minute' => 5, 'total_requests_per_minute' => 2]);
    $limiter = app(EndpointRateLimiter::class);

    $limiter->attempt('my-vllm', $this->workspace->id);
    $limiter->attempt('my-vllm', $this->workspace->id);

    expect($limiter->attempt('my-vllm', $this->other->id))->not->toBeNull()
        ->and(RateLimiter::attempts('endpoint-limit:my-vllm:'.$this->other->id))->toBe(0)
        ->and(RateLimiter::attempts('endpoint-limit:my-vllm:'.$this->workspace->id))->toBe(2);
});

it('counts the calls it turns away, per day', function () {
    limitedEndpoint(['workspace_requests_per_minute' => 1]);
    $limiter = app(EndpointRateLimiter::class);

    $limiter->attempt('my-vllm', $this->workspace->id);
    $limiter->attempt('my-vllm', $this->workspace->id);
    $limiter->attempt('my-vllm', $this->workspace->id);

    expect($limiter->rejectedToday('my-vllm'))->toBe(2);

    $this->travel(1)->day();

    expect($limiter->rejectedToday('my-vllm'))->toBe(0);
});

it('notices a changed limit without a restart', function () {
    $endpoint = limitedEndpoint(['workspace_requests_per_minute' => 1]);
    $limiter = app(EndpointRateLimiter::class);

    $limiter->attempt('my-vllm', $this->workspace->id);
    expect($limiter->attempt('my-vllm', $this->workspace->id))->not->toBeNull();

    $endpoint->update(['workspace_requests_per_minute' => 100]);
    $this->travel(31)->seconds();

    expect($limiter->attempt('my-vllm', $this->workspace->id))->toBeNull();
});

it('applies on every model call through the provider chain', function () {
    limitedEndpoint(['workspace_requests_per_minute' => 1]);
    $byok = app(ByokProviderRegistrar::class);

    [$chain] = $byok->apply(['my-vllm' => 'llama-3-70b'], null, $this->workspace->id, $this->workspace->owner_id);
    expect($chain)->toBe(['my-vllm' => 'llama-3-70b']);

    try {
        $byok->apply(['my-vllm' => 'llama-3-70b'], null, $this->workspace->id, $this->workspace->owner_id);

        throw new LogicException('The second call should have been turned away.');
    } catch (ModelBusyException $e) {
        expect($e->retryAfter)->toBeGreaterThanOrEqual(1)
            ->and($e->getMessage())->toContain('busy right now');
    }

    // Someone else's call is unaffected.
    [$other] = $byok->apply(['my-vllm' => 'llama-3-70b'], null, $this->other->id, $this->other->owner_id);
    expect($other)->toBe(['my-vllm' => 'llama-3-70b']);
});

it('fails over to the next provider instead of refusing, when there is one', function () {
    limitedEndpoint(['workspace_requests_per_minute' => 1]);
    $byok = app(ByokProviderRegistrar::class);
    $chain = ['my-vllm' => 'llama-3-70b', 'openai' => 'gpt-4o'];

    expect($byok->apply($chain, null, $this->workspace->id, $this->workspace->owner_id)[0])->toBe($chain)
        ->and($byok->apply($chain, null, $this->workspace->id, $this->workspace->owner_id)[0])->toBe(['openai' => 'gpt-4o']);
});

it('refuses a single-provider call the same way', function () {
    limitedEndpoint(['workspace_requests_per_minute' => 1]);
    $byok = app(ByokProviderRegistrar::class);

    $byok->apply('my-vllm', null, $this->workspace->id, $this->workspace->owner_id);

    expect(fn () => $byok->apply('my-vllm', null, $this->workspace->id, $this->workspace->owner_id))->toThrow(ModelBusyException::class);
});

it('never limits a workspace\'s call on a provider that is not the operator\'s endpoint', function () {
    limitedEndpoint(['workspace_requests_per_minute' => 1]);
    $byok = app(ByokProviderRegistrar::class);

    foreach (range(1, 5) as $i) {
        expect($byok->apply(['openai' => 'gpt-4o'], null, $this->workspace->id, $this->workspace->owner_id)[0])->toBe(['openai' => 'gpt-4o']);
    }
});

it('answers a real request with a 429 and when to try again', function () {
    limitedEndpoint(['workspace_requests_per_minute' => 1]);
    $model = ModelCatalog::factory()->create(['slug' => 'house-model']);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'my-vllm', 'execution_model_id' => 'llama-3-70b']);
    AdHocPromptAgent::fake(['Hello', 'Hello again']);
    Passport::actingAs($this->workspace->owner);
    $url = "/api/v1/workspaces/{$this->workspace->id}/model-catalog/house-model/try";

    $this->postJson($url, ['prompt' => 'Hi'])->assertOk();

    $response = $this->postJson($url, ['prompt' => 'Hi'])
        ->assertStatus(429)
        ->assertJsonPath('message', 'This model is busy right now. Try again in a moment, or pick another model.');

    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThanOrEqual(1);
});

it('shows up in the health report once it has turned calls away', function () {
    config(['ai.default_for_embeddings' => 'openai']);
    limitedEndpoint(['workspace_requests_per_minute' => 1]);
    $limiter = app(EndpointRateLimiter::class);

    expect(array_column(app(ModelHealthReport::class)->run()['findings'], 'code'))->not->toContain('endpoint_rate_limited');

    $limiter->attempt('my-vllm', $this->workspace->id);
    $limiter->attempt('my-vllm', $this->workspace->id);

    $finding = collect(app(ModelHealthReport::class)->run()['findings'])->firstWhere('code', 'endpoint_rate_limited');

    expect($finding['severity'])->toBe('info')->and($finding['message'])->toContain('1 call(s)');
});
