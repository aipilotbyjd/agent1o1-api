<?php

use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Slack\SlackListChannelJoinsNode;
use App\Nodes\Integrations\Slack\SlackListDirectMessagesNode;
use App\Nodes\Integrations\Slack\SlackListFilesNode;
use App\Nodes\Integrations\Slack\SlackListMentionsNode;
use App\Nodes\Integrations\Slack\SlackListNewChannelsNode;
use App\Nodes\Integrations\Slack\SlackListNewPinsNode;
use App\Nodes\Integrations\Slack\SlackListNewUsersNode;
use App\Services\Triggers\TriggerStatus;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

const SLACK_TRIGGER_BASE = ['access_token' => 'xoxb-test'];

/**
 * A published workflow whose first step is a Slack reader node switched on as
 * a trigger, with Slack push switched on and its subscription synced.
 *
 * @param  array<string, mixed>  $nodeConfig
 */
function slackReaderTrigger(string $nodeType, array $nodeConfig = []): Trigger
{
    config(['triggers.push.slack.enabled' => true, 'triggers.push.slack.signing_secret' => 'sekret']);
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    Http::fake(['https://slack.com/api/conversations.info' => Http::response(['ok' => true, 'channel' => ['name' => 'general', 'is_member' => true]])]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'slack') ?? Connector::factory()->create(['key' => 'slack']))
        ->create(['data' => ['access_token' => 'xoxb-test', 'team_id' => 'T1'], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
    SyncTriggerSubscription::dispatchSync($trigger->id);

    return $trigger->fresh();
}

/**
 * Deliver a Slack event the way Slack does: signed.
 *
 * @param  array<string, mixed>  $event
 */
function slackEvent(array $event, string $eventId): TestResponse
{
    $json = json_encode(['type' => 'event_callback', 'event_id' => $eventId, 'team_id' => 'T1', 'event' => $event]);
    $timestamp = time();

    return test()->call('POST', '/api/hooks/apps/slack', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_SLACK_SIGNATURE' => 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$json}", 'sekret'),
    ], $json);
}

afterEach(fn () => Carbon::setTestNow());

describe('instant delivery: an event wakes the trigger\'s own poll', function () {
    it('polls on a new channel, a new person and a shared file anywhere', function (string $nodeType, array $event) {
        $trigger = slackReaderTrigger($nodeType);

        slackEvent($event, 'Ev-poll')->assertOk();

        Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->trigger->is($trigger));
    })->with([
        'new channel' => ['slack_list_new_channels', ['type' => 'channel_created', 'channel' => ['id' => 'C9', 'name' => 'launch']]],
        'new person' => ['slack_list_new_users', ['type' => 'team_join', 'user' => ['id' => 'U9']]],
        'shared file' => ['slack_list_files', ['type' => 'file_shared', 'file_id' => 'F9', 'channel_id' => 'C1', 'user_id' => 'U1']],
    ]);

    it('polls a channel trigger only for events in its own channel', function (string $nodeType, array $event) {
        $trigger = slackReaderTrigger($nodeType, ['channel' => 'C1']);

        slackEvent([...$event, 'channel' => 'C2'], 'Ev-other')->assertOk();
        Queue::assertNotPushed(PollTrigger::class);

        slackEvent([...$event, 'channel' => 'C1'], 'Ev-mine')->assertOk();
        Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->trigger->is($trigger));
    })->with([
        'someone joined' => ['slack_list_channel_joins', ['type' => 'member_joined_channel', 'user' => 'U9']],
        'app mentioned' => ['slack_list_mentions', ['type' => 'app_mention', 'user' => 'U9', 'text' => '<@UAPP> hi', 'ts' => '5.5']],
    ]);

    it('polls a files trigger limited to a channel only for that channel', function () {
        slackReaderTrigger('slack_list_files', ['channel' => 'C1']);

        slackEvent(['type' => 'file_shared', 'file_id' => 'F9', 'channel_id' => 'C2'], 'Ev-1')->assertOk();
        Queue::assertNotPushed(PollTrigger::class);

        slackEvent(['type' => 'file_shared', 'file_id' => 'F9', 'channel_id' => 'C1'], 'Ev-2')->assertOk();
        Queue::assertPushed(PollTrigger::class);
    });

    it('does not wake a trigger for an event of another kind', function () {
        slackReaderTrigger('slack_list_new_channels');

        slackEvent(['type' => 'team_join', 'user' => ['id' => 'U9']], 'Ev-3')->assertOk();
        slackEvent(['type' => 'message', 'channel' => 'C1', 'user' => 'U1', 'text' => 'hi', 'ts' => '1.1'], 'Ev-4')->assertOk();

        Queue::assertNotPushed(PollTrigger::class);
        Queue::assertNotPushed(FireTriggerEvent::class);
    });
});

describe('what each trigger reads', function () {
    it('lists channels made recently, newest first, across pages', function () {
        Carbon::setTestNow('2026-03-01 10:00:00');
        $now = now()->getTimestamp();
        Http::fake(['slack.com/api/conversations.list*' => Http::sequence()
            ->push(['ok' => true, 'channels' => [['id' => 'C1', 'created' => $now - 3600], ['id' => 'COLD', 'created' => $now - 86400 * 30]], 'response_metadata' => ['next_cursor' => 'next']])
            ->push(['ok' => true, 'channels' => [['id' => 'C2', 'created' => $now - 60]], 'response_metadata' => ['next_cursor' => '']])]);
        $node = new SlackListNewChannelsNode;

        $output = $node->execute(Run::factory()->create(), SLACK_TRIGGER_BASE, []);

        expect(array_column($output['channels'], 'id'))->toBe(['C2', 'C1'])
            ->and($node->triggerItemId($output['channels'][0]))->toBe('C2')
            ->and($node->triggerListsNewestFirst())->toBeTrue()
            ->and($node->triggerName())->toBe('Slack: New channel');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'cursor=next') && str_contains($request->url(), 'exclude_archived=true'));
    });

    it('lists people without bots, deactivated accounts, slackbot or (as asked) guests', function () {
        Http::fake(['slack.com/api/users.list*' => Http::response(['ok' => true, 'members' => [
            ['id' => 'U1', 'name' => 'ada'],
            ['id' => 'U2', 'is_bot' => true],
            ['id' => 'U3', 'deleted' => true],
            ['id' => 'USLACKBOT'],
            ['id' => 'U4', 'is_restricted' => true],
            ['id' => 'U5', 'is_ultra_restricted' => true],
        ]])]);
        $node = new SlackListNewUsersNode;

        $all = $node->execute(Run::factory()->create(), SLACK_TRIGGER_BASE, []);
        $noGuests = $node->execute(Run::factory()->create(), [...SLACK_TRIGGER_BASE, 'include_guests' => false], []);

        expect(array_column($all['members'], 'id'))->toBe(['U1', 'U4', 'U5'])
            ->and(array_column($noGuests['members'], 'id'))->toBe(['U1'])
            ->and($node->triggerName())->toBe('Slack: New person');
    });

    it('lists recent files, narrowed to a channel and a type', function () {
        Carbon::setTestNow('2026-03-01 10:00:00');
        Http::fake(['slack.com/api/files.list*' => Http::response(['ok' => true, 'files' => [['id' => 'F1', 'name' => 'a.pdf']]])]);
        $node = new SlackListFilesNode;

        $node->execute(Run::factory()->create(), [...SLACK_TRIGGER_BASE, 'channel' => 'C1', 'types' => 'pdfs', 'shared_within_hours' => 2], []);
        $node->execute(Run::factory()->create(), [...SLACK_TRIGGER_BASE, 'types' => 'all'], []);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'channel=C1') && str_contains($request->url(), 'types=pdfs') && str_contains($request->url(), 'ts_from='.(now()->getTimestamp() - 7200)));
        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'channel=') && ! str_contains($request->url(), 'types='));
        expect($node->triggerItems(['files' => [['id' => 'F1']]], []))->toHaveCount(1)
            ->and($node->triggerItemId(['id' => 'F1']))->toBe('F1')
            ->and($node->triggerReadConfig(['count' => 5])['count'])->toBe(100);
    });

    it('picks the people who joined out of a channel\'s history', function () {
        Http::fake(['slack.com/api/conversations.history*' => Http::response(['ok' => true, 'messages' => [
            ['type' => 'message', 'subtype' => 'channel_join', 'user' => 'U9', 'ts' => '200.1', 'inviter' => 'U1'],
            ['type' => 'message', 'user' => 'U2', 'text' => 'hello', 'ts' => '150.1'],
            ['type' => 'message', 'subtype' => 'channel_join', 'user' => 'U8', 'ts' => '100.1'],
        ]])]);
        $node = new SlackListChannelJoinsNode;

        $output = $node->execute(Run::factory()->create(), [...SLACK_TRIGGER_BASE, 'channel' => 'C1'], []);

        expect($output['joins'])->toBe([
            ['channel' => 'C1', 'user' => 'U9', 'ts' => '200.1', 'inviter' => 'U1'],
            ['channel' => 'C1', 'user' => 'U8', 'ts' => '100.1', 'inviter' => null],
        ])->and($node->triggerItemId($output['joins'][0]))->toBe('C1:U9:200.1')
            ->and($node->triggerName())->toBe('Slack: Person joined channel');
    });

    it('finds messages that mention the connected app, not bots and not channel events', function () {
        Http::fake([
            'slack.com/api/auth.test' => Http::response(['ok' => true, 'user_id' => 'UAPP']),
            'slack.com/api/conversations.history*' => Http::response(['ok' => true, 'messages' => [
                ['user' => 'U1', 'text' => 'hey <@UAPP> can you help', 'ts' => '300.1'],
                ['user' => 'U1', 'text' => 'hey <@UOTHER>', 'ts' => '290.1'],
                ['bot_id' => 'B1', 'text' => '<@UAPP> from a bot', 'ts' => '280.1'],
                ['user' => 'U1', 'subtype' => 'channel_join', 'text' => '<@UAPP> joined', 'ts' => '270.1'],
            ]]),
        ]);
        $node = new SlackListMentionsNode;

        $output = $node->execute(Run::factory()->create(), [...SLACK_TRIGGER_BASE, 'channel' => 'C1'], []);

        expect(array_column($output['mentions'], 'ts'))->toBe(['300.1'])
            ->and($output['mentions'][0]['channel'])->toBe('C1')
            ->and($node->triggerItemId($output['mentions'][0]))->toBe('300.1')
            ->and($node->triggerName())->toBe('Slack: App mentioned');
    });

    it('looks back the full window when used as a trigger', function () {
        expect((new SlackListChannelJoinsNode)->triggerReadConfig(['joined_within_hours' => 1])['joined_within_hours'])->toBe(6)
            ->and((new SlackListMentionsNode)->triggerReadConfig([])['mentioned_within_hours'])->toBe(6);
    });
});

describe('direct messages, removed reactions and pins', function () {
    it('starts a direct message trigger for a message to the app, once, named by conversation', function () {
        $trigger = slackReaderTrigger('slack_list_direct_messages');

        slackEvent(['type' => 'message', 'channel' => 'D1', 'channel_type' => 'im', 'user' => 'U1', 'text' => 'hello bot', 'ts' => '5.5'], 'Ev-dm1')->assertOk();
        slackEvent(['type' => 'message', 'channel' => 'D2', 'channel_type' => 'im', 'user' => 'U2', 'text' => 'same time', 'ts' => '5.5'], 'Ev-dm2')->assertOk();
        slackEvent(['type' => 'message', 'channel' => 'D1', 'channel_type' => 'im', 'user' => 'U1', 'text' => 'hello bot', 'ts' => '5.5'], 'Ev-dm3')->assertOk();

        expect($trigger->events()->pluck('delivery_id')->sort()->values()->all())->toBe(['D1:5.5', 'D2:5.5']);
    });

    it('does not start a direct message trigger for a channel message, a bot, or an edit', function () {
        $trigger = slackReaderTrigger('slack_list_direct_messages');

        slackEvent(['type' => 'message', 'channel' => 'C1', 'user' => 'U1', 'text' => 'hi', 'ts' => '6.1'], 'Ev-a')->assertOk();
        slackEvent(['type' => 'message', 'channel' => 'D1', 'bot_id' => 'B1', 'text' => 'beep', 'ts' => '6.2'], 'Ev-b')->assertOk();
        slackEvent(['type' => 'message', 'subtype' => 'message_changed', 'channel' => 'D1', 'ts' => '6.3'], 'Ev-c')->assertOk();

        expect($trigger->events()->count())->toBe(0);
    });

    it('reads recent direct messages across conversations, newest first', function () {
        Carbon::setTestNow('2026-03-01 10:00:00');
        Http::fake([
            'slack.com/api/conversations.list*' => Http::response(['ok' => true, 'channels' => [['id' => 'D1', 'user' => 'U1'], ['id' => 'D2', 'user' => 'U2']]]),
            'slack.com/api/conversations.history*channel=D1*' => Http::response(['ok' => true, 'messages' => [['ts' => '100.1', 'text' => 'a']]]),
            'slack.com/api/conversations.history*channel=D2*' => Http::response(['ok' => true, 'messages' => [['ts' => '200.1', 'text' => 'b'], ['ts' => '150.1', 'bot_id' => 'B1', 'text' => 'bot']]]),
        ]);
        $node = new SlackListDirectMessagesNode;

        $output = $node->execute(Run::factory()->create(), SLACK_TRIGGER_BASE, []);

        expect(array_column($output['messages'], 'ts'))->toBe(['200.1', '150.1', '100.1'])
            ->and(array_column($node->triggerItems($output, []), 'ts'))->toBe(['200.1', '100.1'])
            ->and($node->triggerItemId($output['messages'][0]))->toBe('D2:200.1')
            ->and($output['messages'][0]['dm_user'])->toBe('U2');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'types=im'));
    });

    it('starts a trigger for each removal of a reaction, even the same one removed again', function () {
        $trigger = slackReaderTrigger('slack_reaction_removed', ['channel' => 'C1']);
        $removed = ['type' => 'reaction_removed', 'user' => 'U1', 'reaction' => 'eyes', 'item' => ['type' => 'message', 'channel' => 'C1', 'ts' => '7.7']];

        slackEvent([...$removed, 'event_ts' => '8.1'], 'Ev-r1')->assertOk();
        slackEvent([...$removed, 'event_ts' => '9.1'], 'Ev-r2')->assertOk();
        slackEvent([...$removed, 'item' => [...$removed['item'], 'channel' => 'C2'], 'event_ts' => '9.2'], 'Ev-r3')->assertOk();

        expect($trigger->events()->count())->toBe(2)
            ->and($trigger->events()->first()->payload)->toMatchArray(['emoji' => 'eyes', 'removed' => true]);
    });

    it('keeps added and removed reactions apart', function () {
        $added = slackReaderTrigger('slack_reaction_added', ['channel' => 'C1']);
        $removed = slackReaderTrigger('slack_reaction_removed', ['channel' => 'C1']);
        $item = ['user' => 'U1', 'reaction' => 'eyes', 'item' => ['type' => 'message', 'channel' => 'C1', 'ts' => '7.7'], 'event_ts' => '8.1'];

        slackEvent(['type' => 'reaction_removed', ...$item], 'Ev-1')->assertOk();
        expect($added->events()->count())->toBe(0)->and($removed->events()->count())->toBe(1);

        slackEvent(['type' => 'reaction_added', ...$item], 'Ev-2')->assertOk();
        expect($added->events()->count())->toBe(1)->and($removed->events()->count())->toBe(1);
    });

    it('says a removed-reaction trigger needs instant delivery when it has none', function () {
        $trigger = slackReaderTrigger('slack_reaction_removed', ['channel' => 'C1']);
        $working = app(TriggerStatus::class)->for($trigger);

        $trigger->subscription->delete();
        $broken = app(TriggerStatus::class)->for($trigger->fresh());

        expect($working['state'])->toBe('listening')
            ->and($broken['state'])->toBe('needs_push')
            ->and($broken['hint'])->toContain('instant delivery');
    });

    it('polls when something is pinned in the trigger\'s channel only', function () {
        $trigger = slackReaderTrigger('slack_list_new_pins', ['channel' => 'C1']);

        slackEvent(['type' => 'pin_added', 'channel_id' => 'C2', 'user' => 'U1', 'item' => []], 'Ev-p1')->assertOk();
        Queue::assertNotPushed(PollTrigger::class);

        slackEvent(['type' => 'pin_added', 'channel_id' => 'C1', 'user' => 'U1', 'item' => []], 'Ev-p2')->assertOk();
        Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->trigger->is($trigger));
    });

    it('lists pinned messages latest pin first, and names a pin by message and pin time', function () {
        Http::fake(['slack.com/api/pins.list*' => Http::response(['ok' => true, 'items' => [
            ['type' => 'message', 'created' => 100, 'created_by' => 'U1', 'message' => ['ts' => '1.1', 'text' => 'old', 'user' => 'U2']],
            ['type' => 'file', 'created' => 300, 'file' => ['id' => 'F1']],
            ['type' => 'message', 'created' => 200, 'created_by' => 'U3', 'message' => ['ts' => '2.2', 'text' => 'new', 'permalink' => 'https://x']],
        ]])]);
        $node = new SlackListNewPinsNode;

        $output = $node->execute(Run::factory()->create(), [...SLACK_TRIGGER_BASE, 'channel' => 'C1'], []);

        expect(array_column($output['pins'], 'text'))->toBe(['new', 'old'])
            ->and($node->triggerItemId($output['pins'][0]))->toBe('2.2:200')
            ->and($output['pins'][0])->toMatchArray(['channel' => 'C1', 'pinned_by' => 'U3', 'permalink' => 'https://x']);
    });
});
