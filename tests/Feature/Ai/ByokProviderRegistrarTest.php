<?php

use App\Ai\Agents\AdHocPromptAgent;
use App\Models\Ai\AiProviderCredential;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\AiManager;

function byokRegistrarWorkspace(): Workspace
{
    return app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
}

beforeEach(function () {
    config(['ai.providers.openai.key' => 'platform-openai-key', 'ai.providers.anthropic.key' => null]);
});

it('leaves the chain alone when the workspace has no key', function () {
    $workspace = byokRegistrarWorkspace();

    $result = app(ByokProviderRegistrar::class)->apply(['openai' => 'gpt-4o'], null, $workspace->id, null);

    expect($result)->toBe([['openai' => 'gpt-4o'], null]);
});

it('puts a team key ahead of the platform hop it stands in for', function () {
    config(['ai.providers.openrouter.key' => 'platform-openrouter-key']);
    $workspace = byokRegistrarWorkspace();
    $credential = AiProviderCredential::factory()->forWorkspace($workspace)->default()->create(['data' => ['api_key' => 'sk-team']]);

    [$provider, $model] = app(ByokProviderRegistrar::class)->apply(['openai' => 'gpt-4o', 'openrouter' => 'openai/gpt-4o'], null, $workspace->id, null);

    expect($provider)->toBe([
        "byok-{$credential->id}" => 'gpt-4o',
        'openai' => 'gpt-4o',
        'openrouter' => 'openai/gpt-4o',
    ])->and($model)->toBeNull()
        ->and(config("ai.providers.byok-{$credential->id}"))->toMatchArray([
            'driver' => 'openai',
            'key' => 'sk-team',
            'url' => config('ai.providers.openai.url'),
        ]);
});

it('drops the platform hop when the platform has no key for it', function () {
    $workspace = byokRegistrarWorkspace();
    $credential = AiProviderCredential::factory()->forWorkspace($workspace)->provider('anthropic')->create();

    [$provider] = app(ByokProviderRegistrar::class)->apply(['anthropic' => 'claude-sonnet'], null, $workspace->id, null);

    expect($provider)->toBe(["byok-{$credential->id}" => 'claude-sonnet']);
});

it('prefers the member\'s personal key over the team key and ignores other members\' keys', function () {
    $workspace = byokRegistrarWorkspace();
    $member = User::factory()->create();
    $someoneElse = User::factory()->create();
    AiProviderCredential::factory()->forWorkspace($workspace)->default()->create();
    $personal = AiProviderCredential::factory()->forWorkspace($workspace)->personal($member)->create();
    AiProviderCredential::factory()->forWorkspace($workspace)->personal($someoneElse)->create();

    [$provider] = app(ByokProviderRegistrar::class)->apply(['openai' => 'gpt-4o'], null, $workspace->id, $member->id);

    expect(array_key_first($provider))->toBe("byok-{$personal->id}");
});

it('uses only team keys when nobody in particular is acting', function () {
    $workspace = byokRegistrarWorkspace();
    $team = AiProviderCredential::factory()->forWorkspace($workspace)->create();
    AiProviderCredential::factory()->forWorkspace($workspace)->personal(User::factory()->create())->create();

    [$provider] = app(ByokProviderRegistrar::class)->apply(['openai' => 'gpt-4o'], null, $workspace->id, null);

    expect(array_key_first($provider))->toBe("byok-{$team->id}");
});

it('never uses a key that is not known to work', function () {
    $workspace = byokRegistrarWorkspace();
    AiProviderCredential::factory()->forWorkspace($workspace)->invalid()->create();
    AiProviderCredential::factory()->forWorkspace($workspace)->create(['validation_status' => 'unvalidated']);

    expect(app(ByokProviderRegistrar::class)->apply(['openai' => 'gpt-4o'], null, $workspace->id, null))
        ->toBe([['openai' => 'gpt-4o'], null]);
});

it('falls back to a working sibling when the group default is invalid', function () {
    $workspace = byokRegistrarWorkspace();
    AiProviderCredential::factory()->forWorkspace($workspace)->invalid()->default()->create();
    $working = AiProviderCredential::factory()->forWorkspace($workspace)->create();

    [$provider] = app(ByokProviderRegistrar::class)->apply(['openai' => 'gpt-4o'], null, $workspace->id, null);

    expect(array_key_first($provider))->toBe("byok-{$working->id}");
});

it('expands an agent\'s own provider and model columns into a chain', function () {
    $workspace = byokRegistrarWorkspace();
    $credential = AiProviderCredential::factory()->forWorkspace($workspace)->create();

    expect(app(ByokProviderRegistrar::class)->apply('openai', 'gpt-4o-mini', $workspace->id, null))
        ->toBe([["byok-{$credential->id}" => 'gpt-4o-mini', 'openai' => 'gpt-4o-mini'], null]);
});

it('ignores keys for providers outside the BYOK list', function () {
    $workspace = byokRegistrarWorkspace();
    AiProviderCredential::factory()->forWorkspace($workspace)->provider('xkiro')->create();

    expect(app(ByokProviderRegistrar::class)->apply(['xkiro' => 'mistral-small-4'], null, $workspace->id, null))
        ->toBe([['xkiro' => 'mistral-small-4'], null]);
});

it('stamps when a key was last used', function () {
    $workspace = byokRegistrarWorkspace();
    $credential = AiProviderCredential::factory()->forWorkspace($workspace)->create(['last_used_at' => null]);

    app(ByokProviderRegistrar::class)->apply(['openai' => 'gpt-4o'], null, $workspace->id, null);

    expect($credential->fresh()->last_used_at)->not->toBeNull();
});

it('sends the call with the workspace\'s own key and reports it as the serving provider', function () {
    Http::fake(['api.openai.com/v1/responses' => Http::response([
        'id' => 'resp_1',
        'model' => 'gpt-4o',
        'status' => 'completed',
        'output' => [[
            'type' => 'message',
            'id' => 'msg_1',
            'role' => 'assistant',
            'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => 'Hello from your key', 'annotations' => []]],
        ]],
        'usage' => ['input_tokens' => 12, 'output_tokens' => 5, 'total_tokens' => 17],
    ])]);

    $workspace = byokRegistrarWorkspace();
    $credential = AiProviderCredential::factory()->forWorkspace($workspace)->create(['data' => ['api_key' => 'sk-workspace-own']]);

    [$provider] = app(ByokProviderRegistrar::class)->apply(['openai' => 'gpt-4o'], null, $workspace->id, null);
    $response = (new AdHocPromptAgent('Be brief.'))->prompt('Hi', provider: $provider);

    expect($response->text)->toBe('Hello from your key')
        ->and($response->meta->provider)->toBe("byok-{$credential->id}")
        ->and(ByokProviderRegistrar::isByok($response->meta->provider))->toBeTrue();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-workspace-own'));
    Http::assertNotSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer platform-openai-key'));
});

it('picks up a replaced key in the same process', function () {
    $workspace = byokRegistrarWorkspace();
    $credential = AiProviderCredential::factory()->forWorkspace($workspace)->create(['data' => ['api_key' => 'sk-first']]);
    $registrar = app(ByokProviderRegistrar::class);

    $registrar->apply(['openai' => 'gpt-4o'], null, $workspace->id, null);
    $credential->update(['data' => ['api_key' => 'sk-second']]);
    $registrar->apply(['openai' => 'gpt-4o'], null, $workspace->id, null);

    expect(app(AiManager::class)->textProvider("byok-{$credential->id}")->providerCredentials()['key'])->toBe('sk-second');
});

it('skips a hop nobody has a key for, so a workspace without one goes straight to the next', function () {
    config(['ai.providers.openrouter.key' => null]);
    $workspace = byokRegistrarWorkspace();

    expect(app(ByokProviderRegistrar::class)->apply(['openrouter' => 'mistralai/mistral-small-2603', 'openai' => 'gpt-4o'], null, $workspace->id, null))
        ->toBe([['openai' => 'gpt-4o'], null]);
});

it('runs a route the platform has no key for on a workspace that brought one', function () {
    config(['ai.providers.openrouter.key' => null]);
    $workspace = byokRegistrarWorkspace();
    $credential = AiProviderCredential::factory()->forWorkspace($workspace)->provider('openrouter')->create();

    [$provider] = app(ByokProviderRegistrar::class)->apply(['openrouter' => 'mistralai/mistral-small-2603', 'openai' => 'gpt-4o'], null, $workspace->id, null);

    expect($provider)->toBe(["byok-{$credential->id}" => 'mistralai/mistral-small-2603', 'openai' => 'gpt-4o']);
});

it('leaves a chain nobody has any key for untouched', function () {
    config(['ai.providers.openrouter.key' => null, 'ai.providers.openai.key' => null]);
    $workspace = byokRegistrarWorkspace();

    expect(app(ByokProviderRegistrar::class)->apply(['openrouter' => 'x', 'openai' => 'y'], null, $workspace->id, null))
        ->toBe([['openrouter' => 'x', 'openai' => 'y'], null]);
});
