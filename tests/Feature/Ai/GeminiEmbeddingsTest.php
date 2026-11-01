<?php

use App\Models\User;
use App\Services\Agents\KnowledgeBase;
use App\Services\Billing\CreditMeter;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'ai.default_for_embeddings' => 'gemini',
        'ai.providers.gemini.key' => 'platform-gemini-key',
        'ai.providers.gemini.models.embeddings.dimensions' => 768,
    ]);

    Http::fake(['generativelanguage.googleapis.com/*' => fn ($request) => Http::response([
        'embeddings' => array_map(fn () => ['values' => [1.0, 0.0]], $request['requests']),
        'usageMetadata' => ['promptTokenCount' => 1_000_000],
    ])]);

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

it('embeds knowledge with Gemini at the configured size when it is the embeddings provider', function () {
    app(KnowledgeBase::class)->ingest($this->workspace, 'Refunds are issued within 14 days.', 'handbook.md');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'models/gemini-embedding-2:batchEmbedContents')
        && $request['requests'][0]['outputDimensionality'] === 768);
});

it('prices Gemini embeddings from the price table, not the per-token fallback', function () {
    $meter = app(CreditMeter::class);
    $usage = ['prompt_tokens' => 1_000_000, 'provider' => 'gemini'];

    expect($meter->costForEmbeddings([...$usage, 'model' => 'gemini-embedding-2']))
        ->toBeLessThan($meter->costForEmbeddings([...$usage, 'model' => 'unpriced-model']));
});

it('finds knowledge embedded with Gemini', function () {
    $knowledge = app(KnowledgeBase::class);
    $knowledge->ingest($this->workspace, 'Refunds are issued within 14 days.', 'handbook.md');

    expect($knowledge->search($this->workspace, 'refunds'))->not->toBeEmpty();
});
