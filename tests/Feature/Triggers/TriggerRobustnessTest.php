<?php

use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerType;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Agents\Agent;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Triggers\TriggerService;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{0: Workspace, 1: User, 2: Workflow}
 */
function robustWorkflow(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner, Workflow::factory()->forWorkspace($workspace)->create()];
}

/**
 * @param  array<string, mixed>  $config
 */
function robustPublishTrigger(Workflow $workflow, User $owner, string $type, array $config): Trigger
{
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $type, 'config' => $config],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    return Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
}

describe('suspended workspaces', function () {
    beforeEach(function () {
        Queue::fake([FireTriggerEvent::class]);
    });

    it('records an event as skipped without feeding the skip streak', function () {
        [$workspace, $owner, $workflow] = robustWorkflow();
        $trigger = robustPublishTrigger($workflow, $owner, 'webhook_trigger', []);
        $workspace->forceFill(['suspended_at' => now()])->save();

        $event = app(TriggerService::class)->receive($trigger->fresh(), TriggerType::Webhook, ['a' => 1]);

        expect($event->status)->toBe(TriggerEventStatus::Skipped)
            ->and($event->error)->toBe('The workspace is suspended.')
            ->and($trigger->fresh()->consecutive_skip_count)->toBe(0)
            ->and($trigger->fresh()->is_active)->toBeTrue();
        Queue::assertNothingPushed();
    });

    it('keeps firing other workspaces\' schedules when one workspace is suspended', function () {
        [$suspended] = robustWorkflow();
        [$healthy] = robustWorkflow();
        $suspended->forceFill(['suspended_at' => now()])->save();

        $blocked = Trigger::factory()->schedule('* * * * *')->forWorkspace($suspended)->create();
        $running = Trigger::factory()->schedule('* * * * *')->forWorkspace($healthy)->create();

        $this->artisan('triggers:run-due')->assertSuccessful();

        expect($blocked->events()->count())->toBe(0)
            ->and($running->events()->count())->toBe(1);
    });

    it('does not poll for a suspended workspace', function () {
        [$workspace, $owner, $workflow] = robustWorkflow();
        $trigger = robustPublishTrigger($workflow, $owner, 'slack_get_channel_history', ['channel' => 'C1', 'access_token' => 'xoxb-test', 'activate' => true]);
        $workspace->forceFill(['suspended_at' => now()])->save();
        Http::fake();

        dispatch_sync(new PollTrigger($trigger->fresh()));

        Http::assertNothingSent();
    });
});

describe('polling errors', function () {
    beforeEach(function () {
        Queue::fake([FireTriggerEvent::class]);
    });

    it('does not count a rate limit toward the circuit breaker straight away', function () {
        [, $owner, $workflow] = robustWorkflow();
        $trigger = robustPublishTrigger($workflow, $owner, 'slack_get_channel_history', ['channel' => 'C1', 'access_token' => 'xoxb-test', 'activate' => true]);
        Http::fake(['https://slack.com/api/*' => Http::response(['ok' => false, 'error' => 'ratelimited'], 429)]);

        foreach (range(1, 5) as $ignored) {
            dispatch_sync(new PollTrigger($trigger->fresh()));
        }

        expect($trigger->fresh()->consecutive_failure_count)->toBe(0)
            ->and($trigger->fresh()->is_active)->toBeTrue();
    });

    it('counts temporary errors once they have gone on long enough', function () {
        config(['triggers.poll_transient_failures_before_count' => 2]);
        [, $owner, $workflow] = robustWorkflow();
        $trigger = robustPublishTrigger($workflow, $owner, 'slack_get_channel_history', ['channel' => 'C1', 'access_token' => 'xoxb-test', 'activate' => true]);
        Http::fake(['https://slack.com/api/*' => Http::response(['ok' => false, 'error' => 'ratelimited'], 429)]);

        dispatch_sync(new PollTrigger($trigger->fresh()));
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->fresh()->consecutive_failure_count)->toBe(1);
    });

    it('counts an error that will not pass by itself at once', function () {
        [, $owner, $workflow] = robustWorkflow();
        $trigger = robustPublishTrigger($workflow, $owner, 'slack_get_channel_history', ['channel' => 'C1', 'access_token' => 'xoxb-test', 'activate' => true]);
        Http::fake(['https://slack.com/api/*' => Http::response(['ok' => false, 'error' => 'invalid_auth'])]);

        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->fresh()->consecutive_failure_count)->toBe(1);
    });
});

describe('poll order and window', function () {
    beforeEach(function () {
        Queue::fake([FireTriggerEvent::class]);
    });

    it('starts runs oldest first for a source that lists the newest first', function () {
        [, $owner, $workflow] = robustWorkflow();
        $trigger = robustPublishTrigger($workflow, $owner, 'slack_get_channel_history', ['channel' => 'C1', 'access_token' => 'xoxb-test', 'activate' => true]);

        Http::fakeSequence('https://slack.com/api/*')
            ->push(['ok' => true, 'messages' => [['ts' => '1.0', 'text' => 'old']]])
            ->push(['ok' => true, 'messages' => [
                ['ts' => '4.0', 'text' => 'd'],
                ['ts' => '3.0', 'text' => 'c'],
                ['ts' => '2.0', 'text' => 'b'],
                ['ts' => '1.0', 'text' => 'old'],
            ]]);

        dispatch_sync(new PollTrigger($trigger));
        dispatch_sync(new PollTrigger($trigger->fresh()));

        $fired = Queue::pushed(FireTriggerEvent::class)->map(fn (FireTriggerEvent $job): string => $job->event->delivery_id)->all();

        expect($fired)->toBe(['2.0', '3.0', '4.0']);
    });

    it('reads a wider page than the node default when polling', function () {
        $gmail = app(NodeRegistry::class)->resolve('gmail_list_messages');

        expect($gmail->triggerReadConfig(['max_results' => 10])['max_results'])->toBe(100)
            ->and($gmail->triggerReadConfig(['max_results' => 300])['max_results'])->toBe(300);
    });

    it('never starts on a Slack channel event such as someone joining', function () {
        $slack = app(NodeRegistry::class)->resolve('slack_get_channel_history');
        $output = ['messages' => [
            ['ts' => '1.0', 'text' => 'hello'],
            ['ts' => '2.0', 'subtype' => 'channel_join', 'text' => 'joined'],
            ['ts' => '3.0', 'subtype' => 'channel_topic', 'text' => 'new topic'],
            ['ts' => '4.0', 'subtype' => 'file_share', 'text' => 'a file'],
        ]];

        expect(array_column($slack->triggerItems($output, []), 'ts'))->toBe(['1.0', '4.0']);
    });
});

describe('trashing the target', function () {
    it('removes a workflow\'s triggers when it is trashed and makes them again when it is restored', function () {
        [, $owner, $workflow] = robustWorkflow();
        robustPublishTrigger($workflow, $owner, 'webhook_trigger', []);

        $workflow->fresh()->delete();

        expect(Trigger::query()->where('target_id', $workflow->id)->count())->toBe(0);

        $workflow->fresh()->restore();

        expect(Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->count())->toBe(1);
    });

    it('switches an agent\'s triggers off when it is deleted and keeps them', function () {
        [$workspace] = robustWorkflow();
        $agent = Agent::factory()->forWorkspace($workspace)->create();
        $trigger = Trigger::factory()->schedule()->forWorkspace($workspace)->forAgent($agent)->create();

        $agent->delete();

        expect($trigger->fresh()->is_active)->toBeFalse();
    });
});
