<?php

use App\Ai\Assistant\AssistantAgent;
use App\Ai\Assistant\BriefingWriterAgent;
use App\Broadcasting\WorkspaceChannelGate;
use App\Enums\Assistant\AssistantBriefingRunStatus;
use App\Enums\Assistant\AssistantBriefingType;
use App\Enums\Assistant\AssistantSessionOrigin;
use App\Enums\Assistant\AssistantSituationStatus;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\Role;
use App\Events\Assistant\AssistantBriefingCompleted;
use App\Jobs\Assistant\RunBriefingJob;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantBriefingConfig;
use App\Models\Assistant\AssistantBriefingRun;
use App\Models\Assistant\AssistantSituation;
use App\Models\Assistant\AssistantSourceCursor;
use App\Models\Billing\CreditTransaction;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Notifications\Assistant\DailyReportNotification;
use App\Services\Assistant\Briefings\BriefingRunner;
use App\Services\Assistant\Briefings\BriefingScheduler;
use App\Services\Assistant\Briefings\NodeReader;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Priya']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->base = "/api/v1/workspaces/{$this->workspace->id}/assistant";

    $this->config = AssistantBriefingConfig::query()->create([
        'assistant_id' => $this->assistant->id,
        'type' => AssistantBriefingType::Daily,
        'enabled' => true,
        'schedule' => ['time' => '08:00', 'days' => [1, 2, 3, 4, 5, 6, 7], 'timezone' => 'UTC'],
        'connector_scope' => 'all',
        'delivery' => ['email' => false],
    ]);

    $this->connect = function (string $key, string $name): ConnectorCredential {
        $connector = Connector::query()->where('key', $key)->first() ?? Connector::factory()->create(['key' => $key, 'name' => $name]);

        return ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($connector)->create([
            'scope' => ConnectorCredentialScope::Personal,
            'created_by' => $this->owner->id,
            'data' => ['access_token' => "token-{$key}"],
        ]);
    };

    $this->fakeApis = function (bool $githubFails = false): void {
        Http::fake([
            'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::response(['messages' => [['id' => 'm1']]]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/m1*' => Http::response([
                'id' => 'm1',
                'snippet' => 'Can you send the Q3 deck by Friday?',
                'internalDate' => (string) (now()->subHour()->getTimestamp() * 1000),
                'payload' => ['headers' => [['name' => 'From', 'value' => 'Sam <sam@client.test>'], ['name' => 'Subject', 'value' => 'Q3 deck']]],
            ]),
            'api.github.com/*' => $githubFails ? Http::response(['message' => 'Bad credentials'], 401) : Http::response([]),
        ]);
    };

    $this->writerReply = [
        'summary' => 'Sam needs the Q3 deck by Friday.',
        'catch_up' => '- Sam asked for the Q3 deck by Friday (Gmail)',
        'situations' => [[
            'title' => 'Send Sam the Q3 deck',
            'summary' => 'Sam asked for the deck by Friday.',
            'next_step' => 'Draft a reply with the deck attached.',
            'sources' => ['Gmail'],
            'steps' => ['Find the latest Q3 deck', 'Draft a reply to Sam'],
        ]],
    ];

    $this->run = fn (): AssistantBriefingRun => tap(
        $this->config->runs()->create(['run_key' => 'manual:'.fake()->uuid(), 'trigger' => 'manual']),
        fn (AssistantBriefingRun $run) => app(BriefingRunner::class)->run($run),
    )->refresh();
});

it('starts a due report once per local day', function () {
    Queue::fake();
    $this->travelTo(now()->setTimezone('UTC')->setTime(9, 0));

    $scheduler = app(BriefingScheduler::class);

    expect($scheduler->startDue())->toBe(1)
        ->and($scheduler->startDue())->toBe(0)
        ->and($this->config->runs()->sole()->run_key)->toBe('daily:'.now()->toDateString());

    Queue::assertPushed(RunBriefingJob::class, 1);
});

it('does not start a report before its time, on an off day, or while paused', function () {
    Queue::fake();
    $scheduler = app(BriefingScheduler::class);

    $this->travelTo(now()->setTimezone('UTC')->setTime(7, 59));
    expect($scheduler->startDue())->toBe(0);

    $this->travelTo(now()->setTime(9, 0));
    $this->config->update(['schedule' => [...$this->config->schedule, 'days' => [now()->addDay()->dayOfWeekIso]]]);
    expect($scheduler->startDue())->toBe(0);

    $this->config->update(['schedule' => [...$this->config->schedule, 'days' => [1, 2, 3, 4, 5, 6, 7]], 'paused_at' => now()]);
    expect($scheduler->startDue())->toBe(0);
});

it('uses the owner timezone to decide when a report is due', function () {
    Queue::fake();
    $this->config->update(['schedule' => [...$this->config->schedule, 'timezone' => 'Asia/Kolkata']]);
    $this->travelTo(now()->setTimezone('UTC')->setTime(3, 0)); // 08:30 in Kolkata

    expect(app(BriefingScheduler::class)->startDue())->toBe(1);
});

it('writes the report, keeps situations, advances cursors and charges once', function () {
    $this->freezeSecond();
    ($this->connect)('gmail', 'Gmail');
    ($this->fakeApis)();
    BriefingWriterAgent::fake([$this->writerReply]);

    $run = ($this->run)();

    expect($run)
        ->status->toBe(AssistantBriefingRunStatus::Completed)
        ->summary->toBe('Sam needs the Q3 deck by Friday.')
        ->document->toContain('(Gmail)')
        ->delivered_at->not->toBeNull()
        ->and($run->source_results[0])->toMatchArray(['source' => 'gmail', 'ok' => true, 'items' => 1]);

    $situation = AssistantSituation::query()->sole();
    expect($situation)->title->toBe('Send Sam the Q3 deck')->status->toBe(AssistantSituationStatus::Open)
        ->and($situation->steps->pluck('body')->all())->toBe(['Find the latest Q3 deck', 'Draft a reply to Sam']);

    expect(AssistantSourceCursor::query()->where('source', 'gmail')->value('cursor_at'))->not->toBeNull()
        ->and(CreditTransaction::query()->where('source_type', CreditTransactionType::AssistantTurn)->where('source_id', $run->id)->count())->toBe(1);

    BriefingWriterAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Q3 deck') && str_contains($prompt->prompt, 'sam@client.test'));
});

it('looks back 16 hours on the first run and from the cursor afterwards', function () {
    $this->freezeSecond();
    ($this->connect)('gmail', 'Gmail');
    ($this->fakeApis)();
    BriefingWriterAgent::fake([$this->writerReply, $this->writerReply]);

    ($this->run)();
    Http::assertSent(fn (HttpRequest $request) => str_contains(urldecode($request->url()), 'after:'.now()->subHours(16)->getTimestamp()));

    $this->travel(2)->hours();
    ($this->run)();
    Http::assertSent(fn (HttpRequest $request) => str_contains(urldecode($request->url()), 'after:'.now()->subHours(2)->getTimestamp()));
});

it('still writes the report when one app fails, and keeps that app cursor in place', function () {
    ($this->connect)('gmail', 'Gmail');
    ($this->connect)('github', 'GitHub');
    ($this->fakeApis)(githubFails: true);
    BriefingWriterAgent::fake([$this->writerReply]);

    $run = ($this->run)();
    $results = collect($run->source_results)->keyBy('source');

    expect($run->status)->toBe(AssistantBriefingRunStatus::Completed)
        ->and($results['gmail']['ok'])->toBeTrue()
        ->and($results['github']['ok'])->toBeFalse()
        ->and(AssistantSourceCursor::query()->pluck('source')->all())->toBe(['gmail']);
});

it('skips the model when nothing changed', function () {
    ($this->connect)('github', 'GitHub');
    ($this->fakeApis)();
    BriefingWriterAgent::fake()->preventStrayPrompts();

    $run = ($this->run)();

    expect($run)->status->toBe(AssistantBriefingRunStatus::Completed)->summary->toBe('Nothing new since your last report.');
    BriefingWriterAgent::assertNeverPrompted();
});

it('fails with a clear message when no app is connected', function () {
    $run = ($this->run)();

    expect($run)->status->toBe(AssistantBriefingRunStatus::Failed)->error->toContain('No connected apps');
});

it('only reads the apps the owner selected', function () {
    ($this->connect)('gmail', 'Gmail');
    ($this->connect)('github', 'GitHub');
    ($this->fakeApis)();
    $this->config->update(['connector_scope' => 'selected', 'connector_keys' => ['github']]);
    BriefingWriterAgent::fake()->preventStrayPrompts();

    $run = ($this->run)();

    expect(collect($run->source_results)->pluck('source')->all())->toBe(['github']);
    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'gmail'));
});

it('emails the report once when email delivery is on', function () {
    Notification::fake();
    ($this->connect)('gmail', 'Gmail');
    ($this->fakeApis)();
    $this->config->update(['delivery' => ['email' => true]]);
    BriefingWriterAgent::fake([$this->writerReply]);

    $run = ($this->run)();
    app(BriefingRunner::class)->run($run);

    Notification::assertSentToTimes($this->owner, DailyReportNotification::class, 1);
    expect($run->delivery_results)->toBe(['app' => true, 'email' => true]);
});

it('never runs a node that changes something', function () {
    $credential = ($this->connect)('gmail', 'Gmail');

    expect(fn () => app(NodeReader::class)->read('gmail_send_email', $this->assistant, $credential, ['to' => 'a@b.test', 'subject' => 'x', 'body' => 'y']))
        ->toThrow(LogicException::class);
});

it('hands a situation to the assistant as a new conversation', function () {
    AssistantAgent::fake(['On it.']);
    $situation = $this->assistant->situations()->create(['title' => 'Send Sam the Q3 deck', 'summary' => 'Due Friday.']);
    $situation->steps()->create(['position' => 0, 'body' => 'Draft a reply to Sam']);
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/situations/{$situation->id}/send")
        ->assertSuccessful()
        ->assertJsonPath('data.situation.status', 'sent');

    $session = $situation->refresh()->session;
    expect($session->origin)->toBe(AssistantSessionOrigin::Task)
        ->and($session->title)->toBe('Send Sam the Q3 deck');

    AssistantAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Send Sam the Q3 deck') && str_contains($prompt->prompt, '1. Draft a reply to Sam'));
});

it('ticks steps, dismisses situations and hides other members situations', function () {
    $situation = $this->assistant->situations()->create(['title' => 'Review PR']);
    $step = $situation->steps()->create(['position' => 0, 'body' => 'Read the diff']);
    Passport::actingAs($this->owner);

    $this->patchJson("{$this->base}/situations/{$situation->id}/steps/{$step->id}", ['status' => 'done'])
        ->assertSuccessful()
        ->assertJsonPath('data.situation.steps.0.status', 'done');

    $this->patchJson("{$this->base}/situations/{$situation->id}", ['status' => 'dismissed'])->assertSuccessful();
    $this->getJson("{$this->base}/situations")->assertSuccessful()->assertJsonCount(0, 'data.situations');

    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    Passport::actingAs($member);

    $this->patchJson("{$this->base}/situations/{$situation->id}", ['status' => 'open'])->assertNotFound();
});

it('shows and updates the daily report settings and runs it on demand', function () {
    Queue::fake();
    $this->config->delete();
    Passport::actingAs($this->owner);

    $this->getJson("{$this->base}/briefings/daily")
        ->assertSuccessful()
        ->assertJsonPath('data.config.enabled', false)
        ->assertJsonPath('data.config.schedule.time', '08:00');

    $this->putJson("{$this->base}/briefings/daily", [
        'enabled' => true,
        'schedule' => ['time' => '07:30', 'days' => [1, 3, 5], 'timezone' => 'Asia/Kolkata'],
        'delivery' => ['email' => true],
    ])->assertSuccessful()->assertJsonPath('data.config.schedule.timezone', 'Asia/Kolkata');

    $this->putJson("{$this->base}/briefings/daily", ['schedule' => ['time' => '25:00', 'days' => [9], 'timezone' => 'Mars/Base']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule.time', 'schedule.days.0', 'schedule.timezone']);

    $this->postJson("{$this->base}/briefings/daily/run-now")->assertAccepted()->assertJsonPath('data.run.status', 'queued');
    Queue::assertPushed(RunBriefingJob::class);

    $this->postJson("{$this->base}/briefings/daily/pause")->assertSuccessful()->assertJsonPath('data.config.paused', true);
});

it('only lets the owner subscribe to the assistant channel', function () {
    $gate = app(WorkspaceChannelGate::class);
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);

    expect($gate->assistant($this->owner, $this->workspace->id, $this->assistant->id))->toBeTrue()
        ->and($gate->assistant($member, $this->workspace->id, $this->assistant->id))->toBeFalse();
});

it('keeps the report when the live update cannot be broadcast', function () {
    ($this->connect)('github', 'GitHub');
    ($this->fakeApis)();
    Event::listen(AssistantBriefingCompleted::class, fn () => throw new RuntimeException('Reverb is down'));

    expect(($this->run)()->status)->toBe(AssistantBriefingRunStatus::Completed);
});

it('accepts the legacy timezone names browsers still report for the daily report, storing the current name', function () {
    Passport::actingAs($this->owner);

    $this->putJson("{$this->base}/briefings/daily", ['schedule' => ['time' => '08:00', 'days' => [1], 'timezone' => 'Asia/Calcutta']])
        ->assertSuccessful()
        ->assertJsonPath('data.config.schedule.timezone', 'Asia/Kolkata');
});

it('starts a new daily report in the workspace timezone', function () {
    $this->config->delete();
    $this->workspace->update(['timezone' => 'Europe/Berlin']);
    Passport::actingAs($this->owner);

    $this->getJson("{$this->base}/briefings/daily")
        ->assertSuccessful()
        ->assertJsonPath('data.config.schedule.timezone', 'Europe/Berlin');
});
