<?php

use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Agents\AgentMessageRole;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\ToolResultMessage;

function historySession(): AgentSession
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return AgentSession::factory()->forAgent(Agent::factory()->forWorkspace($workspace)->create())->create();
}

it('replays the tool calls and results of an earlier turn before its reply', function () {
    $session = historySession();

    $session->messages()->create(['role' => AgentMessageRole::User, 'content' => 'What is the price of Acme Pro?']);
    $session->messages()->create([
        'role' => AgentMessageRole::Assistant,
        'content' => 'Acme Pro costs $49/month.',
        'tool_calls' => [['id' => 'call_1', 'name' => 'search_knowledge', 'arguments' => ['query' => 'Acme Pro price']]],
        'tool_results' => [['id' => 'call_1', 'name' => 'search_knowledge', 'arguments' => ['query' => 'Acme Pro price'], 'result' => 'Acme Pro: $49/month']],
    ]);

    $messages = array_values(iterator_to_array((new WorkspaceAgent('', $session))->messages()));

    expect($messages)->toHaveCount(4);
    expect($messages[0]->content)->toBe('What is the price of Acme Pro?');
    expect($messages[1])->toBeInstanceOf(AssistantMessage::class);
    expect($messages[1]->toolCalls->first()->name)->toBe('search_knowledge');
    expect($messages[2])->toBeInstanceOf(ToolResultMessage::class);
    expect($messages[2]->toolResults->first()->result)->toBe('Acme Pro: $49/month');
    expect($messages[3])->toBeInstanceOf(AssistantMessage::class);
    expect($messages[3]->content)->toBe('Acme Pro costs $49/month.');
});

it('drops tool calls stored without a result', function () {
    $session = historySession();

    $session->messages()->create([
        'role' => AgentMessageRole::Assistant,
        'content' => 'Done.',
        'tool_calls' => [['id' => 'call_1', 'name' => 'remember', 'arguments' => []]],
    ]);

    $messages = array_values(iterator_to_array((new WorkspaceAgent('', $session))->messages()));

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toBeInstanceOf(AssistantMessage::class);
    expect($messages[0]->toolCalls)->toBeEmpty();
    expect($messages[0]->content)->toBe('Done.');
});

it('cuts a long earlier tool result short when replaying it', function () {
    $session = historySession();
    $page = str_repeat('a', WorkspaceAgent::MAX_REPLAYED_RESULT_CHARS + 500);

    $session->messages()->create([
        'role' => AgentMessageRole::Assistant,
        'content' => 'Summarised.',
        'tool_calls' => [['id' => 'call_1', 'name' => 'web_fetch', 'arguments' => []]],
        'tool_results' => [['id' => 'call_1', 'name' => 'web_fetch', 'arguments' => [], 'result' => $page]],
    ]);

    $messages = array_values(iterator_to_array((new WorkspaceAgent('', $session))->messages()));
    $replayed = $messages[1]->toolResults->first()->result;

    expect($replayed)->toStartWith(str_repeat('a', WorkspaceAgent::MAX_REPLAYED_RESULT_CHARS).'…');
    expect(mb_strlen($replayed))->toBeLessThan(mb_strlen($page));
});
