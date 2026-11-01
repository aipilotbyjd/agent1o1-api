<?php

use App\Ai\Agents\WorkflowExplanationAgent;
use App\Ai\Agents\WorkflowImprovementAgent;
use App\Ai\Agents\WorkflowNodeConfigAgent;
use App\Ai\Agents\WorkflowNodeSuggestionAgent;
use App\Ai\Tools\WorkflowBuilder\SubmitNodeConfigTool;
use App\Ai\Tools\WorkflowBuilder\SubmitNodeSuggestionsTool;
use App\Ai\Tools\WorkflowBuilder\SubmitWorkflowExplanationTool;
use App\Ai\Tools\WorkflowBuilder\SubmitWorkflowImprovementsTool;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Billing\CreditTransaction;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Passport\Passport;

/**
 * @return array{0: string, 1: WorkflowBuilderSession}
 */
function assistSession(array $graph = ['nodes' => [], 'edges' => []]): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create(['draft_graph' => $graph]);

    Passport::actingAs($owner);

    return ["/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/assist", $session];
}

function twoNodeGraph(): array
{
    return [
        'nodes' => [
            ['key' => 'fetch', 'type' => 'call_api', 'config' => ['url' => 'https://example.com', 'method' => 'GET']],
            ['key' => 'shape', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'fetch', 'to' => 'shape', 'condition' => null]],
    ];
}

it('suggests next nodes, dropping types the model invented', function () {
    WorkflowNodeSuggestionAgent::fake([new ToolCall('call_1', SubmitNodeSuggestionsTool::NAME, ['suggestions' => [
        ['type' => 'slack_post_message', 'reason' => 'Tell the team.', 'connect_from' => 'shape'],
        ['type' => 'teleport', 'reason' => 'Not a real node.'],
        ['type' => 'transform', 'reason' => 'Reshape again.', 'connect_from' => 'ghost'],
    ]])]);

    [$url] = assistSession(twoNodeGraph());

    $suggestions = $this->postJson("{$url}/suggest-nodes")->assertOk()->json('data.suggestions');

    expect(array_column($suggestions, 'type'))->toBe(['slack_post_message', 'transform']);
    expect($suggestions[0]['connect_from'])->toBe('shape');
    expect($suggestions[1]['connect_from'])->toBeNull();
    expect(CreditTransaction::query()->where('source_type', CreditTransactionType::WorkflowBuilder)->count())->toBe(1);
});

it('proposes a node config and reports where it breaks the schema', function () {
    WorkflowNodeConfigAgent::fake([new ToolCall('call_1', SubmitNodeConfigTool::NAME, [
        'config_json' => json_encode(['method' => 'GET']),
        'explanation' => 'Fetches the data.',
        'needs_from_user' => ['The API URL'],
    ])]);

    [$url, $session] = assistSession(twoNodeGraph());

    $proposal = $this->postJson("{$url}/configure-node", ['type' => 'call_api', 'instruction' => 'Fetch the orders'])
        ->assertOk()
        ->json('data.proposal');

    expect($proposal['config'])->toBe(['method' => 'GET']);
    expect($proposal['needs_from_user'])->toBe(['The API URL']);
    expect($proposal['errors'])->not->toBe([]);
    // A proposal only: the draft is untouched.
    expect($session->fresh()->draft_lock_version)->toBe(0);
});

it('422s configuring a node the draft does not have', function () {
    [$url] = assistSession(twoNodeGraph());

    $this->postJson("{$url}/configure-node", ['key' => 'ghost', 'instruction' => 'anything'])->assertUnprocessable();
});

it('explains the draft, keeping only steps for real nodes', function () {
    WorkflowExplanationAgent::fake([new ToolCall('call_1', SubmitWorkflowExplanationTool::NAME, [
        'summary' => 'Pulls orders and reshapes them.',
        'steps' => [
            ['key' => 'fetch', 'description' => 'Gets the orders.'],
            ['key' => 'made_up', 'description' => 'Nope.'],
        ],
    ])]);

    [$url] = assistSession(twoNodeGraph());

    $this->postJson("{$url}/explain")
        ->assertOk()
        ->assertJsonPath('data.explanation.summary', 'Pulls orders and reshapes them.')
        ->assertJsonCount(1, 'data.explanation.steps');
});

it('refuses to explain an empty draft without calling the model', function () {
    [$url] = assistSession();

    $this->postJson("{$url}/explain")->assertUnprocessable();

    WorkflowExplanationAgent::assertNeverPrompted();
});

it('suggests improvements, normalizing what the model returns', function () {
    WorkflowImprovementAgent::fake([new ToolCall('call_1', SubmitWorkflowImprovementsTool::NAME, ['improvements' => [
        ['title' => 'Handle API failures', 'description' => 'Add an error edge.', 'priority' => 'urgent', 'node_keys' => ['fetch', 'ghost'], 'suggested_type' => 'slack_post_message'],
        ['title' => '', 'description' => 'Empty title, dropped.'],
    ]])]);

    [$url] = assistSession(twoNodeGraph());

    $improvements = $this->postJson("{$url}/suggest-improvements")->assertOk()->json('data.improvements');

    expect($improvements)->toHaveCount(1);
    expect($improvements[0]['priority'])->toBe('medium');
    expect($improvements[0]['node_keys'])->toBe(['fetch']);
    expect($improvements[0]['suggested_type'])->toBe('slack_post_message');
});

it('does not charge when the model returns no usable answer', function () {
    WorkflowNodeSuggestionAgent::fake(['just prose, no tool call']);

    [$url] = assistSession(twoNodeGraph());

    $this->postJson("{$url}/suggest-nodes")->assertStatus(502);

    expect(CreditTransaction::query()->count())->toBe(0);
});

it('keeps flow-control types in suggestions', function () {
    WorkflowNodeSuggestionAgent::fake([new ToolCall('call_1', SubmitNodeSuggestionsTool::NAME, ['suggestions' => [
        ['type' => 'human_approval', 'reason' => 'Have someone sign off first.', 'connect_from' => 'fetch'],
    ]])]);

    [$url] = assistSession(twoNodeGraph());

    $this->postJson("{$url}/suggest-nodes")
        ->assertOk()
        ->assertJsonPath('data.suggestions.0.type', 'human_approval')
        ->assertJsonPath('data.suggestions.0.name', 'Human Approval');
});
