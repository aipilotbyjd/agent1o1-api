<?php

use App\Ai\Tools\History\ReadPastConversationTool;
use App\Ai\Tools\History\SearchPastConversationsTool;
use App\Enums\Agents\AgentMessageRole;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\User;
use App\Services\Agents\ConversationSearch;
use App\Services\Agents\ToolRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Carbon;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create(['allow_self_clone' => false]);
    $this->current = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id, 'title' => 'Today']);

    $this->conversation = function (?User $user, string $title, array $messages, ?Carbon $at = null): AgentSession {
        $session = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id, 'user_id' => $user?->id, 'title' => $title]);

        foreach ($messages as [$role, $content]) {
            $message = $session->messages()->create(['role' => $role, 'content' => $content]);

            if ($at !== null) {
                $message->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
            }
        }

        return $session;
    };

    $this->search = fn (array $arguments) => json_decode((string) (new SearchPastConversationsTool($this->agent, app(ConversationSearch::class), $this->owner->id, $this->current->id, 'UTC'))
        ->handle(new Request($arguments)), true);
});

it('finds what the agent found before, best match first', function () {
    ($this->conversation)($this->owner, 'Acme research', [
        [AgentMessageRole::User, 'Look into the Acme renewal.'],
        [AgentMessageRole::Assistant, 'The Acme renewal is due in June and they want a 10% discount.'],
    ]);
    ($this->conversation)($this->owner, 'Misc', [
        [AgentMessageRole::Assistant, 'Renewal reminders go out monthly.'],
    ]);

    $results = ($this->search)(['query' => 'Acme renewal discount'])['results'];

    expect($results)->toHaveCount(3);
    expect($results[0]['snippet'])->toContain('10% discount');
    expect($results[0]['conversation_title'])->toBe('Acme research');
    expect($results[0]['score'])->toBeGreaterThan($results[2]['score']);
});

it('never shows another member\'s conversations or the current one', function () {
    $colleague = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $colleague->id, 'role' => Role::Member, 'joined_at' => now()]);

    ($this->conversation)($colleague, 'Theirs', [[AgentMessageRole::Assistant, 'Secret salary figures for Acme.']]);
    $this->current->messages()->create(['role' => AgentMessageRole::User, 'content' => 'Tell me about Acme.']);

    expect((string) (new SearchPastConversationsTool($this->agent, app(ConversationSearch::class), $this->owner->id, $this->current->id, 'UTC'))->handle(new Request(['query' => 'Acme'])))
        ->toContain('Nothing in your earlier conversations');
});

it('narrows matches to a period', function () {
    ($this->conversation)($this->owner, 'Old', [[AgentMessageRole::Assistant, 'Invoice INV-7 was paid.']], Carbon::parse('2026-01-10 12:00'));
    ($this->conversation)($this->owner, 'New', [[AgentMessageRole::Assistant, 'Invoice INV-9 was paid.']], Carbon::parse('2026-03-10 12:00'));

    $results = ($this->search)(['query' => 'invoice paid', 'since' => '2026-03-01', 'until' => '2026-03-31'])['results'];

    expect(array_column($results, 'conversation_title'))->toBe(['New']);
});

it('treats LIKE wildcards in the query as plain text', function () {
    ($this->conversation)($this->owner, 'Exact', [[AgentMessageRole::Assistant, 'The order_id column is empty.']]);
    ($this->conversation)($this->owner, 'Other', [[AgentMessageRole::Assistant, 'The orderXid column is empty.']]);

    $results = ($this->search)(['query' => 'order_id'])['results'];

    expect(array_column($results, 'conversation_title'))->toBe(['Exact']);
});

it('reads a past conversation around a match, but only one of the same person\'s', function () {
    $session = ($this->conversation)($this->owner, 'Acme research', [
        [AgentMessageRole::User, 'Look into the Acme renewal.'],
        [AgentMessageRole::Assistant, 'The Acme renewal is due in June.'],
        [AgentMessageRole::User, 'And the discount?'],
        [AgentMessageRole::Assistant, 'They want 10%.'],
    ]);

    $tool = new ReadPastConversationTool($this->agent, app(ConversationSearch::class), $this->owner->id, 'UTC');
    $read = json_decode((string) $tool->handle(new Request(['conversation_id' => $session->id])), true);

    expect($read['title'])->toBe('Acme research');
    expect(array_column($read['messages'], 'content'))->toBe([
        'Look into the Acme renewal.',
        'The Acme renewal is due in June.',
        'And the discount?',
        'They want 10%.',
    ]);

    $colleague = User::factory()->create();
    $theirs = ($this->conversation)($colleague, 'Theirs', [[AgentMessageRole::User, 'Private.']]);

    expect((string) $tool->handle(new Request(['conversation_id' => $theirs->id])))->toContain('No earlier conversation');
});

it('is only offered once the person has an earlier conversation with the agent', function () {
    $run = $this->current->runs()->create(['workspace_id' => $this->workspace->id, 'trigger_type' => 'manual', 'triggered_by' => $this->owner->id]);
    $names = fn () => collect(app(ToolRegistry::class)->toolsFor($this->agent, $run))->map(fn ($tool) => ToolNameResolver::resolve($tool));

    expect($names())->not->toContain(SearchPastConversationsTool::NAME);

    ($this->conversation)($this->owner, 'Earlier', [[AgentMessageRole::User, 'Hello.']]);

    expect($names())->toContain(SearchPastConversationsTool::NAME)->toContain(ReadPastConversationTool::NAME);
});
