<?php

use App\Ai\Assistant\AssistantAgent;
use App\Enums\Assistant\AssistantSessionOrigin;
use App\Enums\Assistant\AssistantTriggerStatus;
use App\Enums\Assistant\AssistantTriggerType;
use App\Enums\Assistant\AssistantTurnStatus;
use App\Enums\Workspaces\Role;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantTrigger;
use App\Models\Assistant\AssistantTurn;
use App\Models\User;
use App\Notifications\Assistant\TriggerDisabledNotification;
use App\Services\Assistant\Runtime\AssistantLoop;
use App\Services\Assistant\Triggers\TriggerDefinitions;
use App\Services\Assistant\Triggers\TriggerOutcomes;
use App\Services\Assistant\Triggers\TriggerScheduler;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->definitions = app(TriggerDefinitions::class);
    $this->base = "/api/v1/workspaces/{$this->workspace->id}/assistant";

    $this->schedule = fn (array $data = []): AssistantTrigger => $this->definitions->create($this->assistant, [
        'type' => 'schedule', 'name' => 'Morning email check', 'prompt' => 'Summarise my unread email.',
        'cron' => '0 9 * * *', 'timezone' => 'UTC', ...$data,
    ], 'owner');
});

it('works out the next run in the owner timezone', function () {
    $this->travelTo(now()->parse('2026-10-03 00:00:00', 'UTC'));

    $trigger = ($this->schedule)(['timezone' => 'Asia/Kolkata']);

    expect($trigger->next_run_at->toDateTimeString())->toBe('2026-10-03 03:30:00');
});

it('fires a due schedule as a new conversation and moves to the next run', function () {
    AssistantAgent::fake(['You have 3 unread emails.']);
    $this->travelTo(now()->parse('2026-10-03 08:59:00', 'UTC'));
    $trigger = ($this->schedule)();

    expect(app(TriggerScheduler::class)->fireDue())->toBe(0);

    $this->travelTo(now()->parse('2026-10-03 09:00:30', 'UTC'));
    expect(app(TriggerScheduler::class)->fireDue())->toBe(1);

    $session = AssistantSession::query()->sole();
    expect($session)->origin->toBe(AssistantSessionOrigin::Trigger)->assistant_trigger_id->toBe($trigger->id)
        ->and($trigger->refresh()->next_run_at->toDateTimeString())->toBe('2026-10-04 09:00:00');

    AssistantAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Summarise my unread email.') && str_contains($prompt->prompt, 'Morning email check'));
});

it('does not fire paused triggers', function () {
    $this->travelTo(now()->parse('2026-10-03 08:00:00', 'UTC'));
    $trigger = ($this->schedule)();
    $this->definitions->update($trigger, ['status' => 'paused']);
    $this->travelTo(now()->parse('2026-10-03 10:00:00', 'UTC'));

    expect(app(TriggerScheduler::class)->fireDue())->toBe(0);
});

it('runs a one-time trigger once and then removes it', function () {
    AssistantAgent::fake(['Done.']);
    $trigger = $this->definitions->create($this->assistant, [
        'type' => 'once', 'name' => 'Check the deploy', 'prompt' => 'Did the deploy succeed?', 'run_at' => now()->addMinutes(30)->toIso8601String(),
    ], 'assistant');

    $this->travel(31)->minutes();

    expect(app(TriggerScheduler::class)->fireDue())->toBe(1)
        ->and(AssistantTrigger::query()->find($trigger->id))->toBeNull();
});

it('skips a run while the previous one is still going', function () {
    $this->travelTo(now()->parse('2026-10-03 08:00:00', 'UTC'));
    $trigger = ($this->schedule)();
    $previous = AssistantSession::factory()->create(['assistant_id' => $this->assistant->id, 'assistant_trigger_id' => $trigger->id]);
    AssistantTurn::factory()->running()->create(['assistant_session_id' => $previous->id]);
    $this->travelTo(now()->parse('2026-10-03 09:00:30', 'UTC'));

    expect(app(TriggerScheduler::class)->fireDue())->toBe(0)
        ->and(AssistantSession::query()->count())->toBe(1)
        ->and($trigger->refresh()->next_run_at->toDateTimeString())->toBe('2026-10-04 09:00:00');
});

it('rejects schedules that are not valid cron, and past one-time runs', function () {
    expect(fn () => ($this->schedule)(['cron' => 'every morning']))->toThrow(ValidationException::class)
        ->and(fn () => $this->definitions->create($this->assistant, ['type' => 'once', 'name' => 'x', 'prompt' => 'y', 'run_at' => now()->subHour()->toIso8601String()], 'owner'))
        ->toThrow(ValidationException::class);
});

it('fires a webhook trigger with its data and answers at once', function () {
    AssistantAgent::fake(['Logged.']);
    $trigger = $this->definitions->create($this->assistant, [
        'type' => 'webhook', 'name' => 'New lead', 'prompt' => "A new lead came in:\n{{payload}}\nAdd them to my list.",
    ], 'owner');

    $this->postJson("/api/hooks/assistant/{$trigger->webhook_token}", ['email' => 'lead@client.test'])
        ->assertAccepted()
        ->assertJsonPath('accepted', true);

    AssistantAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'lead@client.test') && str_contains($prompt->prompt, 'Add them to my list.'));

    $this->postJson('/api/hooks/assistant/not-a-token', [])->assertNotFound();

    $this->definitions->update($trigger, ['status' => 'paused']);
    $this->postJson("/api/hooks/assistant/{$trigger->webhook_token}", [])->assertConflict();
});

it('switches a trigger off after three failed runs in a row and tells the owner once', function () {
    Notification::fake();
    $trigger = ($this->schedule)();
    $session = AssistantSession::factory()->create(['assistant_id' => $this->assistant->id, 'assistant_trigger_id' => $trigger->id]);
    $outcomes = app(TriggerOutcomes::class);
    $turn = fn (AssistantTurnStatus $status) => AssistantTurn::factory()->create(['assistant_session_id' => $session->id, 'status' => $status, 'error' => 'Gmail connection expired']);

    $outcomes->record($turn(AssistantTurnStatus::Failed));
    $outcomes->record($turn(AssistantTurnStatus::Completed));
    expect($trigger->refresh()->consecutive_failures)->toBe(0);

    foreach (range(1, 4) as $i) {
        $outcomes->record($turn(AssistantTurnStatus::Failed));
    }

    expect($trigger->refresh()->status)->toBe(AssistantTriggerStatus::Disabled);
    Notification::assertSentToTimes($this->owner, TriggerDisabledNotification::class, 1);
});

it('lets the assistant create a trigger from chat once the owner approves', function () {
    AssistantAgent::fake([
        new ToolCall('call_1', 'create_trigger', ['type' => 'schedule', 'name' => 'Weekday digest', 'prompt' => 'Summarise my inbox.', 'cron' => '0 9 * * 1-5', 'timezone' => 'Europe/London']),
        'Done: every weekday at 9:00.',
    ]);
    $session = AssistantSession::factory()->create(['assistant_id' => $this->assistant->id]);
    $loop = app(AssistantLoop::class);

    $turn = $loop->send($session, 'Every weekday at 9 summarise my inbox');
    expect($turn->refresh()->status)->toBe(AssistantTurnStatus::AwaitingApproval)
        ->and(AssistantTrigger::query()->count())->toBe(0);

    $loop->decide($turn, ['call_1' => ['approve' => true]]);

    expect(AssistantTrigger::query()->sole())
        ->name->toBe('Weekday digest')
        ->created_by->toBe('assistant')
        ->type->toBe(AssistantTriggerType::Schedule)
        ->timezone->toBe('Europe/London');
});

it('manages triggers through the API and never exposes the webhook token on its own', function () {
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/triggers", ['type' => 'schedule', 'name' => 'x', 'prompt' => 'y', 'cron' => 'nonsense'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cron');

    $webhook = $this->postJson("{$this->base}/triggers", ['type' => 'webhook', 'name' => 'Lead', 'prompt' => 'Handle {{payload}}'])
        ->assertCreated()
        ->json('data.trigger');

    expect($webhook['webhook_url'])->toContain('/api/hooks/assistant/')
        ->and($webhook)->not->toHaveKey('webhook_token');

    $this->patchJson("{$this->base}/triggers/{$webhook['id']}", ['status' => 'paused'])
        ->assertSuccessful()
        ->assertJsonPath('data.trigger.status', 'paused');

    $this->getJson("{$this->base}/triggers")->assertSuccessful()->assertJsonCount(1, 'data.triggers');
    $this->deleteJson("{$this->base}/triggers/{$webhook['id']}")->assertNoContent();
});

it('keeps triggers private to their owner', function () {
    $trigger = ($this->schedule)();
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    Passport::actingAs($member);

    $this->patchJson("{$this->base}/triggers/{$trigger->id}", ['status' => 'paused'])->assertNotFound();
    $this->postJson("{$this->base}/triggers/{$trigger->id}/run-now")->assertNotFound();
});

it('accepts the legacy timezone names browsers still report, storing the current name', function () {
    $trigger = ($this->schedule)(['timezone' => 'Asia/Calcutta']);

    expect($trigger->fresh()->timezone)->toBe('Asia/Kolkata');
});

it('runs a schedule given no timezone in the workspace timezone', function () {
    $this->workspace->update(['timezone' => 'Asia/Kolkata']);

    $trigger = ($this->schedule)(['timezone' => null]);

    expect($trigger->timezone)->toBe('Asia/Kolkata');
});

it('refuses an unknown timezone', function () {
    ($this->schedule)(['timezone' => 'Mars/Base']);
})->throws(ValidationException::class);
