<?php

use App\Ai\Assistant\AssistantAgent;
use App\Ai\Assistant\Tools\AssistantTool;
use App\Contracts\Assistant\ProvidesAssistantTools;
use App\Enums\Assistant\AssistantActionStatus;
use App\Enums\Assistant\AssistantMessageRole;
use App\Enums\Assistant\AssistantToolEffect;
use App\Enums\Assistant\AssistantToolRule;
use App\Enums\Assistant\AssistantTurnStatus;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Ai\ModelCatalog;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantAction;
use App\Models\Assistant\AssistantMessage;
use App\Models\Assistant\AssistantQueuedInput;
use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantTurn;
use App\Models\Billing\CreditTransaction;
use App\Models\User;
use App\Services\Assistant\Runtime\AssistantLoop;
use App\Services\Assistant\Runtime\ConversationHistory;
use App\Services\Assistant\Tools\ToolCatalog;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Artisan;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;

/**
 * A tool with a chosen effect that counts its runs, offered to the
 * assistant through the `assistant.tools` tag like a real connector.
 */
function fakeAssistantTool(string $name, AssistantToolEffect $effect): AssistantTool
{
    return new class($name, $effect) extends AssistantTool
    {
        public static array $runs = [];

        public function __construct(private string $toolName, private AssistantToolEffect $toolEffect) {}

        public function name(): string
        {
            return $this->toolName;
        }

        public function effect(): AssistantToolEffect
        {
            return $this->toolEffect;
        }

        public function description(): string
        {
            return "Test tool {$this->toolName}.";
        }

        protected function execute(Request $request): string
        {
            self::$runs[] = [$this->toolName, $request->all()];

            return "{$this->toolName} done";
        }

        public function schema(JsonSchema $schema): array
        {
            return ['text' => $schema->string()];
        }
    };
}

function offerAssistantTools(AssistantTool ...$tools): void
{
    app()->instance('test.assistant.tools', new class($tools) implements ProvidesAssistantTools
    {
        public function __construct(private array $tools) {}

        public function toolsFor(Assistant $assistant, AssistantSession $session): array
        {
            return $this->tools;
        }
    });

    app()->tag(['test.assistant.tools'], ToolCatalog::TAG);
}

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Priya']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->session = AssistantSession::factory()->create(['assistant_id' => $this->assistant->id, 'title' => null]);
    $this->loop = app(AssistantLoop::class);
});

it('answers a message and charges the turn', function () {
    AssistantAgent::fake(['Hello Priya!']);

    $turn = $this->loop->send($this->session, 'Say hello');

    expect($turn->refresh())
        ->status->toBe(AssistantTurnStatus::Completed)
        ->and($turn->assistantMessage->content)->toBe('Hello Priya!')
        ->and($this->session->refresh()->title)->toBe('Say hello');

    expect(CreditTransaction::query()->where('source_type', CreditTransactionType::AssistantTurn)->where('source_id', $turn->id)->exists())->toBeTrue();

    AssistantAgent::assertPrompted('Say hello');
});

it('replays answered messages to the model and skips ones a failed turn left unanswered', function () {
    $message = fn (AssistantMessageRole $role, string $content, int $minutesAgo) => $this->session->messages()->create([
        'role' => $role,
        'content' => $content,
        'created_at' => now()->subMinutes($minutesAgo),
    ]);

    $message(AssistantMessageRole::User, 'First question', 10);
    $message(AssistantMessageRole::Assistant, 'First answer.', 9);
    $message(AssistantMessageRole::User, 'A question whose turn failed', 8);
    $current = $message(AssistantMessageRole::User, 'Current question', 1);

    $history = (new ConversationHistory($this->session->refresh(), $current->id))->messages();

    expect(collect($history)->map(fn ($m) => [$m->role->value, $m->content])->all())->toBe([
        ['user', 'First question'],
        ['assistant', 'First answer.'],
    ]);
});

it('puts saved memories in the instructions', function () {
    $this->assistant->memories()->create(['key' => 'preferred_name', 'value' => 'Pri']);
    AssistantAgent::fake(['Hi Pri.']);

    $this->loop->send($this->session, 'Hello');

    AssistantAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->agent->instructions(), 'preferred_name: Pri'));
});

it('saves a memory through the remember tool without asking', function () {
    AssistantAgent::fake([new ToolCall('call_1', 'remember', ['key' => 'team', 'value' => 'Growth']), 'Noted.']);

    $turn = $this->loop->send($this->session, 'I work on the Growth team');

    expect($turn->refresh()->status)->toBe(AssistantTurnStatus::Completed)
        ->and($this->assistant->memories()->where('key', 'team')->value('value'))->toBe('Growth')
        ->and($turn->assistantMessage->tool_calls[0]['name'])->toBe('remember')
        ->and(AssistantAction::query()->count())->toBe(0);
});

it('pauses for approval, then runs the approved call exactly once and finishes', function () {
    $tool = fakeAssistantTool('send_note', AssistantToolEffect::External);
    $tool::$runs = [];
    offerAssistantTools($tool);
    AssistantAgent::fake([new ToolCall('call_1', 'send_note', ['text' => 'hi']), 'Sent it.']);

    $turn = $this->loop->send($this->session, 'Send a note');

    expect($turn->refresh()->status)->toBe(AssistantTurnStatus::AwaitingApproval)
        ->and($tool::$runs)->toBe([])
        ->and($turn->assistantMessage->paused_state['pending_tool_call_ids'])->toBe(['call_1']);

    $action = AssistantAction::query()->sole();
    expect($action)->status->toBe(AssistantActionStatus::Pending)->tool->toBe('send_note');

    $this->loop->decide($turn, ['call_1' => ['approve' => true]]);

    expect($turn->refresh()->status)->toBe(AssistantTurnStatus::Completed)
        ->and($tool::$runs)->toBe([['send_note', ['text' => 'hi']]])
        ->and($action->refresh()->status)->toBe(AssistantActionStatus::Executed)
        ->and($action->result)->toBe('send_note done')
        ->and($turn->assistantMessage->paused_state)->toBeNull()
        ->and($turn->assistantMessage->content)->toContain('Sent it.')
        ->and(AssistantMessage::query()->where('role', AssistantMessageRole::Assistant)->count())->toBe(1);
});

it('does not run a declined call', function () {
    $tool = fakeAssistantTool('send_note', AssistantToolEffect::External);
    $tool::$runs = [];
    offerAssistantTools($tool);
    AssistantAgent::fake([new ToolCall('call_1', 'send_note', ['text' => 'hi']), 'Okay, I did not send it.']);

    $turn = $this->loop->send($this->session, 'Send a note');
    $this->loop->decide($turn, ['call_1' => ['approve' => false, 'note' => 'Not now']]);

    expect($turn->refresh()->status)->toBe(AssistantTurnStatus::Completed)
        ->and($tool::$runs)->toBe([])
        ->and(AssistantAction::query()->sole())->status->toBe(AssistantActionStatus::Rejected)->decision_note->toBe('Not now');
});

it('runs a tool the owner always allows without asking', function () {
    $tool = fakeAssistantTool('send_note', AssistantToolEffect::External);
    $tool::$runs = [];
    offerAssistantTools($tool);
    $this->assistant->toolRules()->create(['tool' => 'send_note', 'rule' => AssistantToolRule::Allow]);
    AssistantAgent::fake([new ToolCall('call_1', 'send_note', ['text' => 'hi']), 'Sent.']);

    $turn = $this->loop->send($this->session, 'Send a note');

    expect($turn->refresh()->status)->toBe(AssistantTurnStatus::Completed)
        ->and($tool::$runs)->toHaveCount(1);
});

it('always asks before a destructive call, whatever the rules say', function () {
    $tool = fakeAssistantTool('delete_file', AssistantToolEffect::Destructive);
    offerAssistantTools($tool);
    $this->assistant->toolRules()->create(['tool' => 'delete_file', 'rule' => AssistantToolRule::Allow]);
    AssistantAgent::fake([new ToolCall('call_1', 'delete_file', ['text' => 'a.pdf']), 'Deleted.']);

    expect($this->loop->send($this->session, 'Delete it')->refresh()->status)->toBe(AssistantTurnStatus::AwaitingApproval);
});

it('never offers a denied tool', function () {
    offerAssistantTools(fakeAssistantTool('send_note', AssistantToolEffect::External));
    $this->assistant->toolRules()->create(['tool' => 'send_note', 'rule' => AssistantToolRule::Deny]);
    AssistantAgent::fake(['Hi']);

    $this->loop->send($this->session, 'Hello');

    AssistantAgent::assertPrompted(fn (AgentPrompt $prompt): bool => ! collect($prompt->agent->tools())
        ->contains(fn ($tool) => $tool instanceof AssistantTool && $tool->name() === 'send_note'));
});

it('gives incognito conversations no memory tools', function () {
    $session = AssistantSession::factory()->incognito()->create(['assistant_id' => $this->assistant->id]);
    AssistantAgent::fake(['Hi']);

    $this->loop->send($session, 'Hello');

    // Searching is fine; nothing that saves memories or knowledge.
    AssistantAgent::assertPrompted(fn (AgentPrompt $prompt): bool => collect($prompt->agent->tools())
        ->filter(fn ($tool) => $tool instanceof AssistantTool)
        ->every(fn (AssistantTool $tool): bool => $tool->effect() === AssistantToolEffect::Read));
});

it('queues a message sent while a turn is running and answers it next', function () {
    AssistantAgent::fake(['Second answer.']);
    $running = AssistantTurn::factory()->running()->create(['assistant_session_id' => $this->session->id]);

    $queued = $this->loop->send($this->session, 'Second question');

    expect($queued)->toBeInstanceOf(AssistantQueuedInput::class);
    AssistantAgent::assertNeverPrompted();

    $running->forceFill(['status' => AssistantTurnStatus::Completed])->save();
    $next = $this->loop->startNextQueued($this->session);

    expect($next->refresh()->status)->toBe(AssistantTurnStatus::Completed)
        ->and($next->userMessage->content)->toBe('Second question')
        ->and($queued->refresh()->consumed_at)->not->toBeNull();
});

it('expires waiting calls when the owner moves on', function () {
    offerAssistantTools(fakeAssistantTool('send_note', AssistantToolEffect::External));
    AssistantAgent::fake([new ToolCall('call_1', 'send_note', ['text' => 'hi']), 'Something else.']);

    $paused = $this->loop->send($this->session, 'Send a note');
    $next = $this->loop->send($this->session, 'Actually, never mind');

    expect($paused->refresh()->status)->toBe(AssistantTurnStatus::Completed)
        ->and(AssistantAction::query()->sole()->status)->toBe(AssistantActionStatus::Expired)
        ->and($next->refresh()->status)->toBe(AssistantTurnStatus::Completed);
});

it('cancels a turn before it starts', function () {
    $turn = AssistantTurn::factory()->create(['assistant_session_id' => $this->session->id]);
    $this->session->queuedInputs()->create(['content' => 'later']);

    $this->loop->cancel($turn);

    expect($turn->refresh()->status)->toBe(AssistantTurnStatus::Cancelled)
        ->and($this->session->queuedInputs()->count())->toBe(0);
});

it('marks a turn failed when the model call errors', function () {
    AssistantAgent::fake(fn () => throw new RuntimeException('provider down'));

    $turn = $this->loop->send($this->session, 'Hello');

    expect($turn->refresh())
        ->status->toBe(AssistantTurnStatus::Failed)
        ->error->toBe('Something went wrong while answering. Please try again.')
        ->assistant_message_id->toBeNull();
});

it('expires undecided actions and resumes their turn', function () {
    offerAssistantTools(fakeAssistantTool('send_note', AssistantToolEffect::External));
    AssistantAgent::fake([new ToolCall('call_1', 'send_note', ['text' => 'hi']), 'Nobody approved, so I did not send it.']);

    $turn = $this->loop->send($this->session, 'Send a note');
    AssistantAction::query()->update(['expires_at' => now()->subMinute()]);

    Artisan::call('assistant:expire-actions');

    expect(AssistantAction::query()->sole()->status)->toBe(AssistantActionStatus::Expired)
        ->and($turn->refresh()->status)->toBe(AssistantTurnStatus::Completed);
});

it('uses the internal assistant catalog entry when the owner has not picked a model', function () {
    $catalog = ModelCatalog::query()->create([
        'slug' => 'personal-assistant',
        'display_name' => 'Personal Assistant',
        'brand' => 'internal',
        'is_active' => true,
        'is_internal' => true,
    ]);
    $catalog->routes()->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 0, 'is_enabled' => true]);
    AssistantAgent::fake(['Hi']);

    $this->loop->send($this->session, 'Hello');

    AssistantAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->model === 'gpt-4o');
});
