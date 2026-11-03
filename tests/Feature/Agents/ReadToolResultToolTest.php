<?php

use App\Ai\Agents\Workspace\WorkspaceAgent;
use App\Ai\Runtime\TranscriptReplay;
use App\Ai\Tools\Workspace\ReadToolResultTool;
use App\Enums\Agents\AgentMessageRole;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Agents\ToolRegistry;
use App\Services\Agents\UntrustedContent;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create();
    $this->session = AgentSession::factory()->forAgent($this->agent)->create();

    $this->storeResult = fn (AgentSession $session, string $id, string $result) => [
        $session->messages()->create(['role' => AgentMessageRole::User, 'content' => 'Fetch the export']),
        $session->messages()->create([
            'role' => AgentMessageRole::Assistant,
            'content' => 'Here is the export.',
            'tool_calls' => [['id' => $id, 'name' => 'call_api', 'arguments' => []]],
            'tool_results' => [['id' => $id, 'name' => 'call_api', 'arguments' => [], 'result' => $result]],
        ]),
    ];
});

it('reads an earlier tool result in full, a page at a time', function () {
    $export = str_repeat('a', 10).str_repeat('b', 10);
    ($this->storeResult)($this->session, 'call-1', $export);

    $tool = new ReadToolResultTool($this->session);

    $first = (string) $tool->handle(new Request(['tool_call_id' => 'call-1', 'length' => 10]));
    expect($first)->toContain('Characters 0-10 of 20')
        ->and($first)->toContain('offset 10')
        ->and($first)->toContain(str_repeat('a', 10))
        ->and($first)->not->toContain('b')
        ->and($first)->toContain('<'.UntrustedContent::TAG);

    $rest = (string) $tool->handle(new Request(['tool_call_id' => 'call-1', 'offset' => 10, 'length' => 10]));
    expect($rest)->toContain('the end of the result')
        ->and($rest)->toContain(str_repeat('b', 10));
});

it('never reads another conversation\'s tool results', function () {
    $other = AgentSession::factory()->forAgent($this->agent)->create();
    ($this->storeResult)($other, 'call-secret', 'someone else\'s data');

    $result = (string) (new ReadToolResultTool($this->session))->handle(new Request(['tool_call_id' => 'call-secret']));

    expect($result)->toContain('No earlier tool result')
        ->and($result)->not->toContain('someone else');
});

it('says so when the offset is past the end', function () {
    ($this->storeResult)($this->session, 'call-1', 'short');

    expect((string) (new ReadToolResultTool($this->session))->handle(new Request(['tool_call_id' => 'call-1', 'offset' => 50])))
        ->toContain('past the end');
});

it('is only attached once the conversation has a tool result to read', function () {
    $run = Run::factory()->forWorkspace($this->workspace)->create();
    $hasReader = fn (): bool => collect(app(ToolRegistry::class)->toolsFor($this->agent, $run, $this->session))
        ->contains(fn ($tool) => $tool instanceof ReadToolResultTool);

    expect($hasReader())->toBeFalse();

    ($this->storeResult)($this->session, 'call-1', 'result');

    expect($hasReader())->toBeTrue();
});

it('points a cut-short result at the reader tool instead of telling the model to call the tool again', function () {
    ($this->storeResult)($this->session, 'call-1', str_repeat('x', WorkspaceAgent::MAX_REPLAYED_RESULT_CHARS + 500));

    $messages = collect((new WorkspaceAgent('', $this->session))->messages());
    $replayed = $messages->first(fn ($message) => $message instanceof ToolResultMessage)->toolResults->first()->result;

    expect($replayed)->toContain(ReadToolResultTool::NAME)
        ->and($replayed)->toContain('"call-1"')
        ->and($replayed)->not->toContain('call the tool again');
});

it('never tells a runtime without the reader tool to repeat a tool that changes something', function () {
    $messages = TranscriptReplay::assistantTurn(
        'Sent.',
        [['id' => 'call-1', 'name' => 'send_email', 'arguments' => []]],
        [['id' => 'call-1', 'name' => 'send_email', 'arguments' => [], 'result' => str_repeat('x', 200)]],
        100,
    );

    $replayed = $messages[1]->toolResults->first()->result;

    expect($replayed)->toContain('never repeat a tool call that changes something');
});
