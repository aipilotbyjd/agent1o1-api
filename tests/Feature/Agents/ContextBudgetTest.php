<?php

use App\Ai\Agents\Workspace\WorkspaceAgent;
use App\Ai\Runtime\ContextBudget;
use App\Enums\Agents\AgentMessageRole;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Ai\ModelCatalog;
use App\Models\User;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Messages\AssistantMessage;

beforeEach(function () {
    $owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $this->session = AgentSession::factory()->forAgent(Agent::factory()->forWorkspace($this->workspace)->create())->create();

    $this->exchange = function (string $question, string $answer): void {
        $this->session->messages()->create(['role' => AgentMessageRole::User, 'content' => $question]);
        $this->session->messages()->create(['role' => AgentMessageRole::Assistant, 'content' => $answer]);
    };

    $this->contents = fn (?int $historyTokens): array => collect((new WorkspaceAgent('', $this->session, historyTokens: $historyTokens))->messages())
        ->map(fn ($message) => $message->content)
        ->all();
});

it('drops the oldest messages that do not fit the budget, starting on a user message', function () {
    ($this->exchange)('old question '.str_repeat('o', 3000), 'old answer');
    ($this->exchange)('middle question', 'middle answer '.str_repeat('m', 3000));
    ($this->exchange)('new question', 'new answer');

    // About 1,000 tokens each for the two long messages: room for one.
    $contents = ($this->contents)(1500);

    expect($contents)->toHaveCount(4)
        ->and($contents[0])->toBe('middle question')
        ->and($contents[3])->toBe('new answer')
        ->and(($this->contents)(null))->toHaveCount(6);
});

it('keeps an exchange whole when it fits, never a reply without its question', function () {
    ($this->exchange)('first', 'first answer');
    ($this->exchange)('second question '.str_repeat('q', 600), 'second answer');
    ($this->exchange)('third', 'third answer');

    // Fits the last exchange and the second answer, not the second question:
    // the orphaned answer is dropped too.
    $contents = ($this->contents)(30);

    expect($contents)->toBe(['third', 'third answer']);
});

it('always keeps the latest exchange, even when it alone is over budget', function () {
    ($this->exchange)('earlier', 'earlier answer');
    ($this->exchange)('huge question '.str_repeat('h', 30000), 'huge answer');

    $messages = collect((new WorkspaceAgent('', $this->session, historyTokens: 10))->messages());

    expect($messages)->toHaveCount(2)
        ->and($messages->last())->toBeInstanceOf(AssistantMessage::class)
        ->and($messages->last()->content)->toBe('huge answer');
});

it('sizes the history from the model\'s window, less what every request carries', function () {
    expect(ContextBudget::historyTokens(100_000, '', 0, 1_000))->toBe(59_000)
        ->and(ContextBudget::historyTokens(100_000, str_repeat('i', 30_000), 10, 1_000))->toBe(59_000 - 10_000 - 4_000)
        ->and(ContextBudget::historyTokens(null, '', 0, null))->toBe((int) (ContextBudget::DEFAULT_WINDOW_TOKENS * ContextBudget::HISTORY_SHARE) - ContextBudget::DEFAULT_OUTPUT_TOKENS)
        ->and(ContextBudget::historyTokens(8_000, str_repeat('i', 30_000), 50, null))->toBe(ContextBudget::MIN_HISTORY_TOKENS);
});

it('reads the context window from the agent\'s catalog entry, and nothing for a raw model', function () {
    $catalog = ModelCatalog::factory()->create(['capabilities' => ['context_window' => 200_000]]);
    $catalogAgent = Agent::factory()->forWorkspace($this->workspace)->create(['model_catalog_id' => $catalog->id]);
    $rawAgent = Agent::factory()->forWorkspace($this->workspace)->create(['model_catalog_id' => null]);

    $resolver = app(ModelCatalogResolver::class);

    expect($resolver->contextWindowFor($catalogAgent))->toBe(200_000)
        ->and($resolver->contextWindowFor($rawAgent))->toBeNull();
});
