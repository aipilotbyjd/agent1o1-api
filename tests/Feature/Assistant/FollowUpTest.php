<?php

use App\Ai\Assistant\Tools\CancelFollowUpTool;
use App\Ai\Assistant\Tools\ListFollowUpsTool;
use App\Ai\Assistant\Tools\TrackFollowUpTool;
use App\Enums\Assistant\AssistantFollowUpStatus;
use App\Enums\Assistant\AssistantSessionOrigin;
use App\Jobs\Assistant\CheckFollowUpJob;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantFollowUp;
use App\Models\Assistant\AssistantInboxConfig;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\User;
use App\Notifications\Assistant\FollowUpReminderNotification;
use App\Services\Assistant\Channels\ChannelReplies;
use App\Services\Assistant\Inbox\FollowUpChecker;
use App\Services\Assistant\Inbox\Mailbox;
use App\Services\Assistant\Inbox\MailboxFactory;
use App\Services\Workspaces\WorkspaceService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Priya']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);

    PlanGrant::factory()->forWorkspace($this->workspace)
        ->forPlan(Plan::factory()->create(['features' => ['smart_inbox' => true]]))
        ->active()
        ->create();

    $this->inbox = $this->assistant->inbox()->create(['provider' => 'gmail', 'enabled' => true, 'enabled_at' => now()]);

    $this->mailbox = Mockery::mock(Mailbox::class);
    app()->instance(MailboxFactory::class, new class($this->mailbox) extends MailboxFactory
    {
        public function __construct(private Mailbox $mailbox) {}

        public function for(AssistantInboxConfig $config): Mailbox
        {
            return $this->mailbox;
        }
    });
});

it('tracks a thread and moves the date when it is tracked again', function () {
    $tool = new TrackFollowUpTool($this->assistant);

    $tool->handle(new Request(['thread_id' => 't1', 'subject' => 'Contract renewal', 'days' => 2]));
    $tool->handle(new Request(['thread_id' => 't1', 'days' => 5]));

    expect($this->assistant->followUps)->toHaveCount(1)
        ->and($this->assistant->followUps->first())
        ->subject->toBe('Contract renewal')
        ->and($this->assistant->followUps->first()->remind_at->isSameDay(now()->addDays(5)))->toBeTrue();
});

it('only tracks while Smart Inbox is on', function () {
    $this->inbox->forceFill(['enabled' => false])->save();

    $result = (new TrackFollowUpTool($this->assistant))->handle(new Request(['thread_id' => 't1']));

    expect($result)->toContain('Smart Inbox')
        ->and($this->assistant->followUps()->count())->toBe(0);
});

it('caps the wait and how many threads are watched', function () {
    config(['assistant.follow_ups.max_days' => 10, 'assistant.follow_ups.max_pending' => 1]);
    $tool = new TrackFollowUpTool($this->assistant);

    $tool->handle(new Request(['thread_id' => 't1', 'days' => 999]));

    expect($this->assistant->followUps()->first()->remind_at->isSameDay(now()->addDays(10)))->toBeTrue()
        ->and($tool->handle(new Request(['thread_id' => 't2'])))->toContain('too many follow-ups')
        ->and($this->assistant->followUps()->count())->toBe(1);
});

it('lists and cancels waiting follow-ups, only the assistant\'s own', function () {
    $mine = AssistantFollowUp::factory()->create(['assistant_id' => $this->assistant->id]);
    $theirs = AssistantFollowUp::factory()->create();

    expect((new ListFollowUpsTool($this->assistant))->handle(new Request([])))->toContain($mine->id)->not->toContain($theirs->id)
        ->and((new CancelFollowUpTool($this->assistant))->handle(new Request(['id' => $theirs->id])))->toContain('No waiting follow-up')
        ->and((new CancelFollowUpTool($this->assistant))->handle(new Request(['id' => $mine->id])))->toContain('Stopped');

    expect($mine->refresh()->status)->toBe(AssistantFollowUpStatus::Cancelled);
});

it('reminds the owner when nobody replied by the date', function () {
    Notification::fake();
    $followUp = AssistantFollowUp::factory()->due()->create(['assistant_id' => $this->assistant->id, 'thread_id' => 't1']);
    $this->mailbox->shouldReceive('threadHasReplyAfter')->once()->with('t1', Mockery::type(CarbonInterface::class))->andReturnFalse();

    $status = app(FollowUpChecker::class)->check($followUp);

    expect($status)->toBe(AssistantFollowUpStatus::Reminded)
        ->and($followUp->refresh()->resolved_at)->not->toBeNull();
    Notification::assertSentTo($this->owner, FollowUpReminderNotification::class);
});

it('stays quiet when someone replied', function () {
    Notification::fake();
    $followUp = AssistantFollowUp::factory()->due()->create(['assistant_id' => $this->assistant->id, 'thread_id' => 't1']);
    $this->mailbox->shouldReceive('threadHasReplyAfter')->once()->andReturnTrue();

    expect(app(FollowUpChecker::class)->check($followUp))->toBe(AssistantFollowUpStatus::Replied);
    Notification::assertNothingSent();
});

it('does not check a follow-up before its date', function () {
    $followUp = AssistantFollowUp::factory()->create(['assistant_id' => $this->assistant->id]);
    $this->mailbox->shouldNotReceive('threadHasReplyAfter');

    expect(app(FollowUpChecker::class)->check($followUp))->toBe(AssistantFollowUpStatus::Pending);
});

it('keeps a follow-up pending when the mailbox cannot be read', function () {
    Notification::fake();
    $followUp = AssistantFollowUp::factory()->due()->create(['assistant_id' => $this->assistant->id]);
    $this->mailbox->shouldReceive('threadHasReplyAfter')->andThrow(new RuntimeException('Gmail is down'));

    expect(app(FollowUpChecker::class)->check($followUp))->toBe(AssistantFollowUpStatus::Pending);
    Notification::assertNothingSent();
});

it('drops follow-ups once Smart Inbox is switched off', function () {
    $this->inbox->forceFill(['enabled' => false])->save();
    $followUp = AssistantFollowUp::factory()->due()->create(['assistant_id' => $this->assistant->id]);

    expect(app(FollowUpChecker::class)->check($followUp->refresh()))->toBe(AssistantFollowUpStatus::Cancelled);
});

it('queues a check only for follow-ups that are due', function () {
    Bus::fake();
    $due = AssistantFollowUp::factory()->due()->create(['assistant_id' => $this->assistant->id]);
    AssistantFollowUp::factory()->create(['assistant_id' => $this->assistant->id]);
    AssistantFollowUp::factory()->due()->create(['assistant_id' => $this->assistant->id, 'status' => AssistantFollowUpStatus::Replied]);

    $this->artisan('assistant:check-follow-ups')->assertSuccessful();

    Bus::assertDispatchedTimes(CheckFollowUpJob::class, 1);
    Bus::assertDispatched(CheckFollowUpJob::class, fn (CheckFollowUpJob $job): bool => $job->followUp->is($due));
});

it('also sends the in-app notification', function () {
    expect((new FollowUpReminderNotification(AssistantFollowUp::factory()->create(['assistant_id' => $this->assistant->id])))->via($this->owner))
        ->toBe(['mail', 'database']);
});

it('reminds in the Slack or text conversation the follow-up was asked for in', function (AssistantSessionOrigin $origin, int $expectedMessages) {
    Notification::fake();
    $session = $this->assistant->sessions()->create(['origin' => $origin, 'external_thread_ref' => 'ref', 'last_activity_at' => now()]);
    $followUp = AssistantFollowUp::factory()->due()->create(['assistant_id' => $this->assistant->id, 'assistant_session_id' => $session->id, 'subject' => 'Renewal']);
    $this->mailbox->shouldReceive('threadHasReplyAfter')->andReturnFalse();

    $channels = Mockery::mock(ChannelReplies::class);
    $channels->shouldReceive('say')->times($expectedMessages)->with(Mockery::on(fn ($given) => $given->is($session)), Mockery::pattern('/Renewal/'));
    app()->instance(ChannelReplies::class, $channels);

    app(FollowUpChecker::class)->check($followUp);
})->with([
    'slack' => [AssistantSessionOrigin::Slack, 1],
    'sms' => [AssistantSessionOrigin::Sms, 1],
    'email (the reminder email is enough)' => [AssistantSessionOrigin::Email, 0],
    'web' => [AssistantSessionOrigin::Web, 0],
]);
