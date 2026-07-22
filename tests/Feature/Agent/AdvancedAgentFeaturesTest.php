<?php

use App\Agents\AgentRunner;
use App\Agents\Tools\AgentTool;
use App\Agents\Tools\CachedTool;
use App\Agents\Tools\CodeExecutionTool;
use App\Agents\Tools\WebBrowseTool;
use App\Enums\Role;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Agent\AgentBudgetService;
use App\Services\Agent\AgentEvalService;
use App\Services\Agent\AgentVersionService;
use App\Services\Agent\ConnectorTemplateService;
use App\Services\AgentService;
use Database\Seeders\PlanSeeder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);

    $this->agent = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Tool wiring in AgentRunner
|--------------------------------------------------------------------------
*/

test('code execution and web browse tools are only registered when enabled', function () {
    $off = (new AgentRunner)->build($this->agent, 'hi')->tools();
    expect(collect($off)->contains(fn ($t) => $t instanceof CodeExecutionTool))->toBeFalse()
        ->and(collect($off)->contains(fn ($t) => $t instanceof WebBrowseTool))->toBeFalse();

    $this->agent->update(['code_execution_enabled' => true, 'web_browsing_enabled' => true]);

    $on = (new AgentRunner)->build($this->agent->fresh(), 'hi')->tools();
    expect(collect($on)->contains(fn ($t) => $t instanceof CodeExecutionTool))->toBeTrue()
        ->and(collect($on)->contains(fn ($t) => $t instanceof WebBrowseTool))->toBeTrue();
});

test('child agents are exposed as delegation tools', function () {
    $child = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'name' => 'Billing Agent',
    ]);

    $this->agent->update(['child_agent_ids' => [$child->id]]);

    $tools = (new AgentRunner)->build($this->agent->fresh(), 'hi')->tools();
    $agentTools = collect($tools)->filter(fn ($t) => $t instanceof AgentTool);

    expect($agentTools)->toHaveCount(1)
        ->and((string) $agentTools->first()->description())->toContain('Billing Agent');
});

test('sub-agent delegation is one level deep to avoid cycles', function () {
    $child = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'child_agent_ids' => [$this->agent->id],
    ]);

    // When the runner builds the child in sub-agent mode, it must NOT expose
    // further AgentTools (which would loop back to the parent).
    $tools = (new AgentRunner)->build($child->fresh(), 'hi', ['allow_sub_agents' => false])->tools();

    expect(collect($tools)->contains(fn ($t) => $t instanceof AgentTool))->toBeFalse();
});

test('read-only tools are wrapped for caching when tool_cache_enabled', function () {
    $this->agent->update(['web_browsing_enabled' => true, 'tool_cache_enabled' => true]);

    $tools = (new AgentRunner)->build($this->agent->fresh(), 'hi')->tools();

    expect(collect($tools)->contains(fn ($t) => $t instanceof CachedTool))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| CachedTool
|--------------------------------------------------------------------------
*/

test('cached tool returns the first result for identical arguments', function () {
    $calls = 0;
    $inner = new class($calls) implements Tool
    {
        public function __construct(public int &$calls) {}

        public function name(): string
        {
            return 'counter';
        }

        public function description(): Stringable|string
        {
            return 'counts calls';
        }

        public function handle(Request $request): Stringable|string
        {
            $this->calls++;

            return 'result-'.$this->calls;
        }

        public function schema(JsonSchema $schema): array
        {
            return [];
        }
    };

    $cached = new CachedTool($inner);

    $first = (string) $cached->handle(new Request(['q' => 'x']));
    $second = (string) $cached->handle(new Request(['q' => 'x']));
    $different = (string) $cached->handle(new Request(['q' => 'y']));

    expect($first)->toBe('result-1')
        ->and($second)->toBe('result-1') // served from cache
        ->and($different)->toBe('result-2')
        ->and($calls)->toBe(2)
        ->and($cached->name())->toBe('counter');
});

/*
|--------------------------------------------------------------------------
| CodeExecutionTool
|--------------------------------------------------------------------------
*/

test('code execution tool runs python and returns stdout', function () {
    $tool = new CodeExecutionTool;
    $result = json_decode((string) $tool->handle(new Request([
        'language' => 'python',
        'code' => 'print(6 * 7)',
    ])), true);

    expect($result['stdout'] ?? '')->toContain('42');
})->skip(fn () => ! trim(shell_exec('command -v python3') ?? ''), 'python3 not available');

test('code execution tool rejects unknown languages', function () {
    $tool = new CodeExecutionTool;
    $result = json_decode((string) $tool->handle(new Request([
        'language' => 'ruby',
        'code' => 'puts 1',
    ])), true);

    expect($result['error'] ?? '')->toContain('Unsupported language');
});

/*
|--------------------------------------------------------------------------
| WebBrowseTool SSRF guard
|--------------------------------------------------------------------------
*/

test('web browse tool refuses non-public and non-http urls', function () {
    $tool = new WebBrowseTool;

    foreach (['http://127.0.0.1/', 'http://localhost/admin', 'file:///etc/passwd', 'ftp://example.com'] as $url) {
        $result = json_decode((string) $tool->handle(new Request(['url' => $url])), true);
        expect($result['error'] ?? '')->toContain('Refused');
    }
});

/*
|--------------------------------------------------------------------------
| Connector templates
|--------------------------------------------------------------------------
*/

test('connector template converts to a tool config', function () {
    $service = new ConnectorTemplateService;

    $config = $service->toToolConfig('slack_send_message');

    expect($config)->toMatchArray([
        'node_type' => 'slack',
        'tool_name' => 'send_slack_message',
        'is_enabled' => true,
    ])->and($service->toToolConfig('nope'))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Budget guardrails
|--------------------------------------------------------------------------
*/

test('budget service blocks a paused agent', function () {
    $budgets = app(AgentBudgetService::class);
    expect($budgets->blockReason($this->agent))->toBeNull();

    $this->agent->update(['is_paused' => true, 'paused_reason' => 'nope']);
    expect($budgets->blockReason($this->agent->fresh()))->toBe('nope');
});

test('budget service blocks once the daily token budget is spent', function () {
    $budgets = app(AgentBudgetService::class);
    $this->agent->update(['daily_token_budget' => 1000]);

    AgentRun::create([
        'agent_id' => $this->agent->id,
        'workspace_id' => $this->workspace->id,
        'status' => 'completed',
        'total_tokens' => 1200,
    ]);

    expect($budgets->blockReason($this->agent->fresh()))->toContain('Daily token budget');
});

test('settling a run over the daily budget auto-pauses the agent', function () {
    $budgets = app(AgentBudgetService::class);
    $this->agent->update(['daily_token_budget' => 500]);

    $run = AgentRun::create([
        'agent_id' => $this->agent->id,
        'workspace_id' => $this->workspace->id,
        'status' => 'completed',
        'model' => 'claude-sonnet-5',
        'prompt_tokens' => 400,
        'completion_tokens' => 300,
        'total_tokens' => 700,
    ]);

    $budgets->settleRun($this->agent, $run);

    expect($this->agent->fresh()->is_paused)->toBeTrue()
        ->and((float) $run->fresh()->estimated_cost)->toBeGreaterThan(0);
});

test('cost estimate uses the model price table', function () {
    $budgets = app(AgentBudgetService::class);

    // 1M input @ $3 + 1M output @ $15 = $18 for sonnet.
    $cost = $budgets->estimateCost('claude-sonnet-5', 1_000_000, 1_000_000);

    expect($cost)->toBe(18.0);
});

/*
|--------------------------------------------------------------------------
| Versioning & rollback
|--------------------------------------------------------------------------
*/

test('creating an agent records an initial version', function () {
    $agent = app(AgentService::class)->create($this->workspace, $this->user, [
        'name' => 'Versioned',
        'instructions' => 'Original instructions.',
    ]);

    expect($agent->versions()->count())->toBe(1)
        ->and($agent->versions()->first()->snapshot['instructions'])->toBe('Original instructions.');
});

test('rollback restores a previous snapshot as a new version', function () {
    $service = app(AgentVersionService::class);

    $this->agent->update(['instructions' => 'v1 instructions']);
    $v1 = $service->record($this->agent->fresh());

    $this->agent->update(['instructions' => 'v2 instructions']);
    $service->record($this->agent->fresh());

    $restored = $service->rollback($this->agent->fresh(), $v1, $this->user);

    expect($restored->instructions)->toBe('v1 instructions')
        // rollback is additive — it appends a new version, never rewrites history.
        ->and($restored->versions()->count())->toBeGreaterThanOrEqual(3);
});

/*
|--------------------------------------------------------------------------
| Eval framework — deterministic assertions
|--------------------------------------------------------------------------
*/

test('eval run grades deterministic assertions against the agent output', function () {
    // Stub the runner so no real model is called; it always answers the same.
    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')->andReturn('Your refund of $42 has been processed.');
    app()->instance(AgentRunner::class, $runner);

    $suite = $this->agent->evalSuites()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Refund suite',
    ]);
    $suite->cases()->create([
        'name' => 'mentions refund',
        'input' => 'Where is my refund?',
        'assertions' => [
            ['type' => 'contains', 'value' => 'refund'],
            ['type' => 'regex', 'value' => '\\$\\d+'],
        ],
        'sort_order' => 0,
    ]);
    $suite->cases()->create([
        'name' => 'should fail',
        'input' => 'Where is my refund?',
        'assertions' => [
            ['type' => 'not_contains', 'value' => 'refund'],
        ],
        'sort_order' => 1,
    ]);

    $run = app(AgentEvalService::class)->run($suite->load(['agent', 'cases']), $this->user);

    expect($run->status)->toBe('completed')
        ->and($run->total)->toBe(2)
        ->and($run->passed)->toBe(1)
        ->and($run->failed)->toBe(1);
});

test('version diff reports changed fields', function () {
    $service = app(AgentVersionService::class);

    $before = $service->snapshot($this->agent);
    $this->agent->update(['instructions' => 'changed', 'planning_enabled' => true]);
    $after = $service->snapshot($this->agent->fresh());

    $diff = $service->diff($before, $after);

    expect($diff)->toHaveKeys(['instructions', 'planning_enabled'])
        ->and($diff['planning_enabled']['to'])->toBeTrue();
});
