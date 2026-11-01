<?php

use App\Ai\Tools\NodeTool;
use App\Contracts\NodeContract;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Agents\AgentToolBinding;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\Plan;
use App\Models\Runs\Run;
use App\Models\User;
use App\Nodes\DataTransform\RunCodeNode;
use App\Nodes\DataTransform\TransformNode;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\EmbeddingsResponse;
use Laravel\Ai\Tools\Request;
use Laravel\Passport\Passport;

it('charges the fixed cost of a node an agent runs as a tool', function () {
    $run = Run::factory()->create();

    $tool = new NodeTool(new RunCodeNode, new AgentToolBinding(['node_type' => 'run_code']), $run);

    $tool->handle(new Request([
        'operations' => [['op' => 'set', 'output' => 'x', 'value' => 'y']],
    ]));

    $transaction = CreditTransaction::sole();

    expect($transaction->source_type)->toBe(CreditTransactionType::AgentToolNode);
    expect($transaction->workspace_id)->toBe($run->workspace_id);
    expect($transaction->credits)->toBe(3);
});

it('charges the tokens an AI node spends when an agent runs it as a tool', function () {
    $run = Run::factory()->create();

    $aiNode = new class implements NodeContract
    {
        public function type(): string
        {
            return 'ask_ai';
        }

        public function category(): string
        {
            return 'ai_automation';
        }

        public function name(): string
        {
            return 'Ask AI';
        }

        public function description(): string
        {
            return 'Stub AI node.';
        }

        public function configSchema(): array
        {
            return ['type' => 'object', 'properties' => ['prompt' => ['type' => 'string']]];
        }

        public function execute(Run $run, array $config, array $context): array
        {
            return ['text' => 'answer', 'usage' => ['prompt_tokens' => 1500, 'completion_tokens' => 500]];
        }
    };

    (new NodeTool($aiNode, new AgentToolBinding(['node_type' => 'ask_ai']), $run))
        ->handle(new Request(['prompt' => 'hi']));

    // 2,000 unpriced tokens at the flat 1,000-tokens-per-credit fallback.
    expect(CreditTransaction::sole()->credits)->toBe(2);
});

it('charges nothing extra for a node whose only cost is its tool call', function () {
    $tool = new NodeTool(new TransformNode, new AgentToolBinding(['node_type' => 'transform']), Run::factory()->create());

    $tool->handle(new Request(['mapping' => []]));

    expect(CreditTransaction::count())->toBe(0);
});

it('charges knowledge ingestion for its embedding tokens', function () {
    Embeddings::fake([
        new EmbeddingsResponse([[1.0, 0.0]], 500_000, new Meta('openai', 'text-embedding-3-small')),
    ]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", [
        'text' => 'Refunds are issued within 14 days.',
        'source' => 'handbook.md',
    ])->assertCreated();

    $transaction = CreditTransaction::sole();

    // 500k tokens at $0.02/1M is $0.01: two $0.005 credits.
    expect($transaction->source_type)->toBe(CreditTransactionType::KnowledgeIngestion);
    expect($transaction->source_id)->toBe(DocumentEmbedding::sole()->id);
    expect($transaction->credits)->toBe(2);
    expect($transaction->reason)->toBe('Knowledge ingestion (handbook.md)');
    expect($workspace->currentUsagePeriod()->credits_used)->toBe(2);
});

it('refuses knowledge ingestion for a workspace out of credits', function () {
    Embeddings::fake();
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 1]);
    config(['billing.default_plan' => 'free']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $period = $workspace->currentUsagePeriod();
    $period->forceFill(['credits_used' => $period->credits_limit])->save();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", [
        'text' => 'Refunds are issued within 14 days.',
    ])->assertStatus(402);

    expect(DocumentEmbedding::count())->toBe(0);
});
