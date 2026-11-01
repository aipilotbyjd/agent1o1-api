<?php

use App\Models\Ai\AiProviderCredential;
use App\Models\Billing\CreditTransaction;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\KnowledgeBase;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['ai.default_for_embeddings' => 'openai', 'ai.providers.openai.key' => 'platform-openai-key']);

    Http::fake(['api.openai.com/v1/embeddings' => fn ($request) => Http::response([
        'data' => array_map(fn () => ['embedding' => [1.0, 0.0]], (array) $request['input']),
        'usage' => ['prompt_tokens' => 500_000],
    ])]);

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

function embeddingsSentWith(string $key): bool
{
    return Http::recorded(fn ($request) => str_ends_with($request->url(), '/embeddings')
        && $request->hasHeader('Authorization', "Bearer {$key}"))->isNotEmpty();
}

it('embeds shared knowledge on the workspace\'s team key and charges no credits for it', function () {
    AiProviderCredential::factory()->forWorkspace($this->workspace)->create(['data' => ['api_key' => 'sk-team-key']]);

    app(KnowledgeBase::class)->ingest($this->workspace, 'Refunds are issued within 14 days.', 'handbook.md');

    expect(embeddingsSentWith('sk-team-key'))->toBeTrue()
        ->and(embeddingsSentWith('platform-openai-key'))->toBeFalse()
        ->and(CreditTransaction::count())->toBe(0);
});

it('embeds a member\'s private knowledge on their personal key', function () {
    $member = User::factory()->create();
    AiProviderCredential::factory()->forWorkspace($this->workspace)->create(['data' => ['api_key' => 'sk-team-key']]);
    AiProviderCredential::factory()->forWorkspace($this->workspace)->personal($member)->create(['data' => ['api_key' => 'sk-member-key']]);

    app(KnowledgeBase::class)->ingest($this->workspace, 'My salary target is private.', 'notes.md', ownerId: $member->id);

    expect(embeddingsSentWith('sk-member-key'))->toBeTrue()
        ->and(embeddingsSentWith('sk-team-key'))->toBeFalse();
});

it('embeds a search query on the searching member\'s key, or the team key when nobody is searching', function () {
    $member = User::factory()->create();
    AiProviderCredential::factory()->forWorkspace($this->workspace)->create(['data' => ['api_key' => 'sk-team-key']]);
    AiProviderCredential::factory()->forWorkspace($this->workspace)->personal($member)->create(['data' => ['api_key' => 'sk-member-key']]);
    $knowledge = app(KnowledgeBase::class);

    $knowledge->search($this->workspace, 'refunds', viewer: $member);
    expect(embeddingsSentWith('sk-member-key'))->toBeTrue();

    Http::fake(['api.openai.com/v1/embeddings' => Http::response(['data' => [['embedding' => [1.0, 0.0]]], 'usage' => ['prompt_tokens' => 1]])]);
    $knowledge->search($this->workspace, 'refunds');
    expect(embeddingsSentWith('sk-team-key'))->toBeTrue();
});

it('keeps embedding on the platform key and billing credits when the workspace has no key', function () {
    app(KnowledgeBase::class)->ingest($this->workspace, 'Refunds are issued within 14 days.', 'handbook.md');

    expect(embeddingsSentWith('platform-openai-key'))->toBeTrue()
        ->and(CreditTransaction::count())->toBe(1);
});

it('only ever stands in for the platform\'s own embeddings provider, never switches models', function () {
    $workspace = Workspace::find($this->workspace->id);
    AiProviderCredential::factory()->forWorkspace($workspace)->provider('anthropic')->create();
    $credential = AiProviderCredential::factory()->forWorkspace($workspace)->create();
    $registrar = app(ByokProviderRegistrar::class);

    expect($registrar->embeddingsProvider($workspace->id, null))->toBe(["byok-{$credential->id}" => null, 'openai' => null]);

    $credential->delete();

    expect($registrar->embeddingsProvider($workspace->id, null))->toBe('openai');
});
