<?php

use App\Actions\Agents\CreateAgentSessionAction;
use App\Ai\Tools\Workspace\NodeTool;
use App\Enums\Agents\AutonomyMode;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Agents\Approvals\ActionGuard;
use App\Services\Agents\ToolRegistry;
use App\Services\Http\SsrfGuard;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    app()->instance(SsrfGuard::class, new SsrfGuard(fn () => ['203.0.113.10']));
    $this->attempts = 0;
    Http::fake(function () {
        $this->attempts++;

        throw new ConnectionException('Connection refused');
    });

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create(['autonomy_mode' => AutonomyMode::Autopilot]);
    $this->agent->toolBindings()->create([
        'node_type' => 'call_api',
        'config' => ['url' => 'https://hooks.acme.test/notify', 'method' => 'POST'],
        'exposed_fields' => ['body'],
        'approval_policy' => ['mode' => 'allow'],
    ]);

    $this->session = app(CreateAgentSessionAction::class)->execute($this->agent, $this->owner);
    $this->chatRun = $this->session->runs()->create([
        'workspace_id' => $this->workspace->id,
        'trigger_type' => 'manual',
        'input' => ['message' => 'Post it'],
        'triggered_by' => $this->owner->id,
    ]);

    $this->nodeTool = fn (Run $run): NodeTool => collect(app(ToolRegistry::class)->toolsFor($this->agent, $run))
        ->first(fn ($tool) => $tool instanceof NodeTool);

    $this->call = fn (NodeTool $tool, string $id): string => (string) $tool->handle(new Request(['body' => ['text' => 'hi']], $id));
});

it('refuses a tool that keeps failing once it reaches the limit, without running it again', function () {
    $tool = ($this->nodeTool)($this->chatRun);

    foreach (range(1, ActionGuard::MAX_FAILURES_PER_TURN) as $attempt) {
        expect(($this->call)($tool, "call-{$attempt}"))->toContain('Connection refused');
    }

    $attemptsBefore = $this->attempts;
    $refused = ($this->call)($tool, 'call-last');

    expect($refused)->toContain('already failed several times')
        ->and($refused)->toContain('Stop calling this tool')
        ->and(json_decode($refused, true))->toHaveKey('note')
        ->and($this->attempts)->toBe($attemptsBefore);
});

it('keeps counting a chat turn\'s failures after its tools are rebuilt, as on resume', function () {
    $tool = ($this->nodeTool)($this->chatRun);

    foreach (range(1, ActionGuard::MAX_FAILURES_PER_TURN) as $attempt) {
        ($this->call)($tool, "call-{$attempt}");
    }

    $rebuilt = ($this->nodeTool)($this->chatRun);

    expect(($this->call)($rebuilt, 'call-after-resume'))->toContain('already failed several times');
});

it('does not carry failures over between calls that share a run but not a turn, such as eval cases', function () {
    $evalRun = Run::factory()->forWorkspace($this->workspace)->create(['triggered_by' => $this->owner->id]);
    $tool = ($this->nodeTool)($evalRun);

    foreach (range(1, ActionGuard::MAX_FAILURES_PER_TURN) as $attempt) {
        ($this->call)($tool, "case-one-{$attempt}");
    }

    $nextCase = ($this->nodeTool)($evalRun);

    expect(($this->call)($nextCase, 'case-two-1'))->toContain('Connection refused');
});
