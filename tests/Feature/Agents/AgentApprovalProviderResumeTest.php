<?php

use App\Actions\Agents\ResolveAgentActionsAction;
use App\Enums\Agents\AgentActionStatus;
use App\Enums\Agents\AutonomyMode;
use App\Enums\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Agents\AgentRunner;
use App\Services\Http\SsrfGuard;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * An Anthropic streaming body: a resumed turn is streamed, so its faked
 * reply has to arrive as server-sent events rather than one JSON message.
 */
function anthropicStream(string $id, string $text, int $inputTokens, int $outputTokens): string
{
    $events = [
        'message_start' => ['type' => 'message_start', 'message' => ['id' => $id, 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-test', 'content' => [], 'stop_reason' => null, 'usage' => ['input_tokens' => $inputTokens, 'output_tokens' => 1]]],
        'content_block_start' => ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']],
        'content_block_delta' => ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => $text]],
        'content_block_stop' => ['type' => 'content_block_stop', 'index' => 0],
        'message_delta' => ['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn', 'stop_sequence' => null], 'usage' => ['output_tokens' => $outputTokens]],
        'message_stop' => ['type' => 'message_stop'],
    ];

    return collect($events)->map(fn (array $data, string $event): string => "event: {$event}\ndata: ".json_encode($data)."\n\n")->implode('');
}

/**
 * The SDK's agent fakes skip approval resumption entirely, so this drives a
 * paused turn through the real Anthropic gateway (HTTP faked) to prove the
 * resume is accepted: the paused message replays with its call still
 * unanswered, the decision matches it, and the provider is handed the
 * action's real result, which ran once, before the resume.
 */
it('resumes a paused turn through the real provider with the approved action\'s result', function () {
    config(['ai.providers.anthropic.key' => 'test-key']);
    app()->instance(SsrfGuard::class, new SsrfGuard(fn () => ['203.0.113.10']));

    Http::fake([
        'hooks.acme.test/*' => Http::response(['delivered' => true]),
        'api.anthropic.com/*' => Http::sequence()
            ->push([
                'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-test',
                'content' => [
                    ['type' => 'text', 'text' => 'I will post it.'],
                    ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'call_api', 'input' => ['body' => ['text' => 'Deploy done']]],
                ],
                'stop_reason' => 'tool_use',
                'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
            ])
            ->push(anthropicStream('msg_2', 'Posted to the team.', inputTokens: 40, outputTokens: 6), headers: ['Content-Type' => 'text/event-stream']),
    ]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create([
        'autonomy_mode' => AutonomyMode::Ask,
        'provider' => 'anthropic',
        'model' => 'claude-test',
        'allow_web_fetch' => false,
    ]);
    $agent->toolBindings()->create([
        'node_type' => 'call_api',
        'config' => ['url' => 'https://hooks.acme.test/notify', 'method' => 'POST'],
        'exposed_fields' => ['body'],
    ]);
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    $paused = app(AgentRunner::class)->run($session, 'Tell the team the deploy is done.');

    expect(Run::query()->where('runnable_id', $session->id)->sole()->status)->toBe(RunStatus::AwaitingApproval);
    expect($paused->paused_state['provider_content_blocks'])->not->toBeEmpty();

    app(ResolveAgentActionsAction::class)->execute($owner, [['action_id' => AgentAction::query()->sole()->id, 'decision' => 'approve']]);

    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Executed);
    expect(Run::query()->where('runnable_id', $session->id)->sole()->status)->toBe(RunStatus::Completed);
    expect($paused->fresh()->content)->toBe("I will post it.\n\nPosted to the team.");

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'hooks.acme.test'));

    $resumeRequest = collect(Http::recorded())
        ->map(fn (array $pair) => $pair[0])
        ->filter(fn (Request $request) => str_contains($request->url(), 'api.anthropic.com'))
        ->last();

    $toolResult = collect($resumeRequest['messages'])
        ->flatMap(fn (array $message) => is_array($message['content']) ? $message['content'] : [])
        ->firstWhere('type', 'tool_result');

    expect($toolResult['tool_use_id'])->toBe('toolu_1');
    expect(json_encode($toolResult['content']))->toContain('delivered');
});

it('ends the turn without another model call when a rejection says to stop', function () {
    config(['ai.providers.anthropic.key' => 'test-key']);
    app()->instance(SsrfGuard::class, new SsrfGuard(fn () => ['203.0.113.10']));

    Http::fake([
        'hooks.acme.test/*' => Http::response(['delivered' => true]),
        'api.anthropic.com/*' => Http::sequence()->push([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-test',
            'content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'call_api', 'input' => ['body' => ['text' => 'hi']]]],
            'stop_reason' => 'tool_use',
            'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
        ]),
    ]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create([
        'autonomy_mode' => AutonomyMode::Ask,
        'provider' => 'anthropic',
        'model' => 'claude-test',
        'allow_web_fetch' => false,
    ]);
    $agent->toolBindings()->create([
        'node_type' => 'call_api',
        'config' => ['url' => 'https://hooks.acme.test/notify', 'method' => 'POST'],
        'exposed_fields' => ['body'],
    ]);
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    app(AgentRunner::class)->run($session, 'Post it.');

    app(ResolveAgentActionsAction::class)->execute($owner, [['action_id' => AgentAction::query()->sole()->id, 'decision' => 'reject', 'stop' => true]]);

    Http::assertSentCount(1);
    expect(Run::query()->where('runnable_id', $session->id)->sole()->status)->toBe(RunStatus::Completed);
    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Rejected);
});
