<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\Slack\SlackCreateChannelNode;
use App\Nodes\Integrations\Slack\SlackDeleteMessageNode;
use App\Nodes\Integrations\Slack\SlackGetChannelHistoryNode;
use App\Nodes\Integrations\Slack\SlackInviteToChannelNode;
use App\Nodes\Integrations\Slack\SlackListChannelsNode;
use App\Nodes\Integrations\Slack\SlackListUsersNode;
use App\Nodes\Integrations\Slack\SlackPostMessageNode;
use App\Nodes\Integrations\Slack\SlackScheduleMessageNode;
use App\Nodes\Integrations\Slack\SlackSendDirectMessageNode;
use App\Nodes\Integrations\Slack\SlackUploadFileNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

it('registers every slack node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $types = [
        'slack_post_message' => SlackPostMessageNode::class,
        'slack_list_channels' => SlackListChannelsNode::class,
        'slack_create_channel' => SlackCreateChannelNode::class,
        'slack_invite_to_channel' => SlackInviteToChannelNode::class,
        'slack_get_channel_history' => SlackGetChannelHistoryNode::class,
        'slack_upload_file' => SlackUploadFileNode::class,
        'slack_list_users' => SlackListUsersNode::class,
    ];

    foreach ($types as $type => $class) {
        expect($registry->has($type))->toBeTrue();
        $node = $registry->resolve($type);
        expect($node)->toBeInstanceOf($class);
        expect($node->category())->toBe('slack');
    }
});

it('posts a message with the bearer token and required fields', function () {
    Http::fake(['slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '123.456', 'channel' => 'C1'])]);

    $node = new SlackPostMessageNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'access_token' => 'xoxb-test',
        'channel' => 'C1',
        'text' => 'hello',
    ], []);

    expect($output)->toBe(['ok' => true, 'ts' => '123.456', 'channel' => 'C1']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://slack.com/api/chat.postMessage'
            && $request->hasHeader('Authorization', 'Bearer xoxb-test')
            && $request['channel'] === 'C1'
            && $request['text'] === 'hello';
    });
});

it('throws when slack answers ok:false, even with an HTTP 200', function () {
    Http::fake(['slack.com/api/chat.postMessage' => Http::response(['ok' => false, 'error' => 'channel_not_found'])]);

    $node = new SlackPostMessageNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['access_token' => 'xoxb-test', 'channel' => 'C1', 'text' => 'hi'], []))
        ->toThrow(RuntimeException::class, 'channel_not_found');
});

it('throws when access_token is missing', function () {
    $node = new SlackPostMessageNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['channel' => 'C1', 'text' => 'hi'], []))
        ->toThrow(RuntimeException::class, 'access_token');
});

it('lists channels with optional filters as query params', function () {
    Http::fake(['slack.com/api/conversations.list*' => Http::response(['ok' => true, 'channels' => []])]);

    $node = new SlackListChannelsNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'xoxb-test', 'types' => 'public_channel', 'limit' => 50], []);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'conversations.list')
            && $request['types'] === 'public_channel'
            && $request['limit'] == 50;
    });
});

it('creates a channel', function () {
    Http::fake(['slack.com/api/conversations.create' => Http::response(['ok' => true, 'channel' => ['id' => 'C2']])]);

    $node = new SlackCreateChannelNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'xoxb-test', 'name' => 'new-channel'], []);

    expect($output['channel']['id'])->toBe('C2');
});

it('invites users to a channel', function () {
    Http::fake(['slack.com/api/conversations.invite' => Http::response(['ok' => true, 'channel' => ['id' => 'C1']])]);

    $node = new SlackInviteToChannelNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'xoxb-test', 'channel' => 'C1', 'users' => 'U1,U2'], []);

    Http::assertSent(fn ($request) => $request['users'] === 'U1,U2');
});

it('gets channel history', function () {
    Http::fake(['slack.com/api/conversations.history*' => Http::response(['ok' => true, 'messages' => [['text' => 'hi']]])]);

    $node = new SlackGetChannelHistoryNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'xoxb-test', 'channel' => 'C1', 'limit' => 10], []);

    expect($output['messages'])->toBe([['text' => 'hi']]);
});

it('uploads a file in the three steps Slack now needs', function () {
    Http::fake([
        'slack.com/api/files.getUploadURLExternal*' => Http::response(['ok' => true, 'upload_url' => 'https://files.slack.com/upload/v1/abc', 'file_id' => 'F1']),
        'files.slack.com/upload/v1/abc' => Http::response('OK - 14'),
        'slack.com/api/files.completeUploadExternal' => Http::response(['ok' => true, 'files' => [['id' => 'F1', 'title' => 'Notes']]]),
    ]);

    $output = (new SlackUploadFileNode)->execute(Run::factory()->create(), [
        'access_token' => 'xoxb-test',
        'channels' => ['C1', 'C2'],
        'content' => 'file body text',
        'filename' => 'notes.txt',
        'title' => 'Notes',
        'initial_comment' => 'Here you go',
    ], []);

    expect($output['file']['id'])->toBe('F1')->and($output['files'])->toHaveCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'files.getUploadURLExternal')
        && str_contains($request->url(), 'filename=notes.txt')
        && str_contains($request->url(), 'length=14'));
    Http::assertSent(fn ($request) => $request->url() === 'https://files.slack.com/upload/v1/abc'
        && $request->body() === 'file body text'
        && ! $request->hasHeader('Authorization'));
    Http::assertSent(fn ($request) => str_ends_with($request->url(), 'files.completeUploadExternal')
        && $request['files'] === [['id' => 'F1', 'title' => 'Notes']]
        && $request['channels'] === 'C1,C2'
        && $request['initial_comment'] === 'Here you go');
});

it('uploads with a default file name and no comment', function () {
    Http::fake([
        'slack.com/api/files.getUploadURLExternal*' => Http::response(['ok' => true, 'upload_url' => 'https://files.slack.com/upload/v1/abc', 'file_id' => 'F2']),
        'files.slack.com/*' => Http::response('OK'),
        'slack.com/api/files.completeUploadExternal' => Http::response(['ok' => true, 'files' => [['id' => 'F2']]]),
    ]);

    (new SlackUploadFileNode)->execute(Run::factory()->create(), ['access_token' => 'xoxb-test', 'channels' => 'C1', 'content' => 'x'], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'filename=upload.txt'));
    Http::assertSent(fn ($request) => str_ends_with($request->url(), 'completeUploadExternal') && ! isset($request['initial_comment']) && $request['files'] === [['id' => 'F2']]);
});

it('stops when the upload address refuses the file', function () {
    Http::fake([
        'slack.com/api/files.getUploadURLExternal*' => Http::response(['ok' => true, 'upload_url' => 'https://files.slack.com/upload/v1/abc', 'file_id' => 'F3']),
        'files.slack.com/*' => Http::response('nope', 500),
    ]);

    (new SlackUploadFileNode)->execute(Run::factory()->create(), ['access_token' => 'xoxb-test', 'channels' => 'C1', 'content' => 'x'], []);
})->throws(RuntimeException::class, 'the upload address answered 500');

it('lists users', function () {
    Http::fake(['slack.com/api/users.list*' => Http::response(['ok' => true, 'members' => [['id' => 'U1']]])]);

    $node = new SlackListUsersNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'xoxb-test'], []);

    expect($output['members'])->toBe([['id' => 'U1']]);
});

const SLACK_BASE = ['access_token' => 'xoxb-test'];

dataset('slack actions', [
    'update message' => ['SlackUpdateMessageNode', ['channel' => 'C1', 'ts' => '1.1', 'text' => 'new'], 'POST', 'chat.update', ['channel' => 'C1', 'ts' => '1.1', 'text' => 'new']],
    'delete message' => ['SlackDeleteMessageNode', ['channel' => 'C1', 'ts' => '1.1'], 'POST', 'chat.delete', ['channel' => 'C1', 'ts' => '1.1']],
    'add reaction' => ['SlackAddReactionNode', ['channel' => 'C1', 'ts' => '1.1', 'emoji' => ':white_check_mark:'], 'POST', 'reactions.add', ['channel' => 'C1', 'timestamp' => '1.1', 'name' => 'white_check_mark']],
    'remove reaction' => ['SlackRemoveReactionNode', ['channel' => 'C1', 'ts' => '1.1', 'emoji' => 'eyes'], 'POST', 'reactions.remove', ['channel' => 'C1', 'timestamp' => '1.1', 'name' => 'eyes']],
    'message link' => ['SlackGetMessageLinkNode', ['channel' => 'C1', 'ts' => '1.1'], 'GET', 'chat.getPermalink', ['channel' => 'C1', 'message_ts' => '1.1']],
    'ephemeral message' => ['SlackSendEphemeralMessageNode', ['channel' => 'C1', 'user' => 'U1', 'text' => 'psst'], 'POST', 'chat.postEphemeral', ['channel' => 'C1', 'user' => 'U1', 'text' => 'psst']],
    'thread replies' => ['SlackGetThreadRepliesNode', ['channel' => 'C1', 'thread_ts' => '1.1'], 'GET', 'conversations.replies', ['channel' => 'C1', 'ts' => '1.1', 'limit' => 100]],
    'channel info' => ['SlackGetChannelInfoNode', ['channel' => 'C1'], 'GET', 'conversations.info', ['channel' => 'C1', 'include_num_members' => 'true']],
    'join channel' => ['SlackJoinChannelNode', ['channel' => 'C1'], 'POST', 'conversations.join', ['channel' => 'C1']],
    'archive channel' => ['SlackArchiveChannelNode', ['channel' => 'C1'], 'POST', 'conversations.archive', ['channel' => 'C1']],
    'rename channel' => ['SlackRenameChannelNode', ['channel' => 'C1', 'name' => 'launch'], 'POST', 'conversations.rename', ['channel' => 'C1', 'name' => 'launch']],
    'channel topic' => ['SlackSetChannelTopicNode', ['channel' => 'C1', 'topic' => 'Ship it'], 'POST', 'conversations.setTopic', ['channel' => 'C1', 'topic' => 'Ship it']],
    'channel purpose' => ['SlackSetChannelPurposeNode', ['channel' => 'C1', 'purpose' => 'Launch work'], 'POST', 'conversations.setPurpose', ['channel' => 'C1', 'purpose' => 'Launch work']],
    'remove from channel' => ['SlackRemoveFromChannelNode', ['channel' => 'C1', 'user' => 'U1'], 'POST', 'conversations.kick', ['channel' => 'C1', 'user' => 'U1']],
    'pin message' => ['SlackPinMessageNode', ['channel' => 'C1', 'ts' => '1.1'], 'POST', 'pins.add', ['channel' => 'C1', 'timestamp' => '1.1']],
    'unpin message' => ['SlackUnpinMessageNode', ['channel' => 'C1', 'ts' => '1.1'], 'POST', 'pins.remove', ['channel' => 'C1', 'timestamp' => '1.1']],
    'get user' => ['SlackGetUserNode', ['user' => 'U1'], 'GET', 'users.info', ['user' => 'U1']],
    'find user by email' => ['SlackFindUserByEmailNode', ['email' => ' ada@example.com '], 'GET', 'users.lookupByEmail', ['email' => 'ada@example.com']],
]);

it('calls the right Slack method with the right fields', function (string $class, array $config, string $method, string $endpoint, array $expected) {
    Http::fake(['slack.com/api/*' => Http::response(['ok' => true])]);
    $node = app("App\\Nodes\\Integrations\\Slack\\{$class}");

    $node->execute(Run::factory()->create(), [...SLACK_BASE, ...$config], []);

    Http::assertSent(fn ($request) => $request->method() === $method
        && str_contains($request->url(), "slack.com/api/{$endpoint}")
        && collect($expected)->every(fn ($value, $key) => (string) $request[$key] === (string) $value));
})->with('slack actions');

it('registers every new slack node under the slack category', function () {
    $registry = app(NodeRegistry::class);

    foreach (['send_direct_message', 'update_message', 'delete_message', 'add_reaction', 'remove_reaction', 'get_message_link', 'send_ephemeral_message',
        'get_thread_replies', 'get_channel_info', 'join_channel', 'archive_channel', 'rename_channel', 'set_channel_topic', 'set_channel_purpose',
        'remove_from_channel', 'pin_message', 'unpin_message', 'get_user', 'find_user_by_email', 'schedule_message',
        'list_new_channels', 'list_new_users', 'list_files', 'list_channel_joins', 'list_mentions'] as $suffix) {
        expect($registry->resolve("slack_{$suffix}")->category())->toBe('slack');
    }
});

it('marks deleting, archiving and removing as destructive and reading as read', function () {
    $effect = fn (string $class) => app("App\\Nodes\\Integrations\\Slack\\{$class}")->effect([])->value;

    expect($effect('SlackDeleteMessageNode'))->toBe('destructive')
        ->and($effect('SlackArchiveChannelNode'))->toBe('destructive')
        ->and($effect('SlackRemoveFromChannelNode'))->toBe('destructive')
        ->and($effect('SlackSendDirectMessageNode'))->toBe('external')
        ->and($effect('SlackGetUserNode'))->toBe('read');
});

it('opens the direct message conversation first, then posts into it', function () {
    Http::fake([
        'slack.com/api/conversations.open' => Http::response(['ok' => true, 'channel' => ['id' => 'D9']]),
        'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '1.1']),
    ]);

    $output = app(SlackSendDirectMessageNode::class)->execute(Run::factory()->create(), [...SLACK_BASE, 'user' => 'U1', 'text' => 'hi'], []);

    expect($output['ts'])->toBe('1.1');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), 'conversations.open') && $request['users'] === 'U1');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), 'chat.postMessage') && $request['channel'] === 'D9' && $request['text'] === 'hi');
});

it('schedules a message for a time as a unix timestamp', function () {
    Http::fake(['slack.com/api/*' => Http::response(['ok' => true, 'scheduled_message_id' => 'Q1'])]);

    app(SlackScheduleMessageNode::class)->execute(Run::factory()->create(), [
        ...SLACK_BASE, 'channel' => 'C1', 'text' => 'later', 'post_at' => '2030-01-01T09:00:00Z',
    ], []);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), 'chat.scheduleMessage') && $request['post_at'] === 1893488400 && ! isset($request['thread_ts']));
});

it('posts rich blocks, a broadcast reply and no link previews only when asked', function () {
    Http::fake(['slack.com/api/*' => Http::response(['ok' => true])]);
    $node = new SlackPostMessageNode;

    $node->execute(Run::factory()->create(), [...SLACK_BASE, 'channel' => 'C1', 'text' => 'plain'], []);
    $node->execute(Run::factory()->create(), [
        ...SLACK_BASE, 'channel' => 'C1', 'text' => 'rich', 'thread_ts' => '1.1', 'reply_broadcast' => true, 'unfurl_links' => false,
        'blocks' => [['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '*hi*']]],
    ], []);

    Http::assertSent(fn ($request) => $request['text'] === 'plain' && $request->data() === ['channel' => 'C1', 'text' => 'plain']);
    Http::assertSent(fn ($request) => $request['text'] === 'rich'
        && $request['reply_broadcast'] === true && $request['unfurl_links'] === false && $request['blocks'][0]['type'] === 'section');
});

it('does not broadcast a message that is not a thread reply', function () {
    Http::fake(['slack.com/api/*' => Http::response(['ok' => true])]);

    (new SlackPostMessageNode)->execute(Run::factory()->create(), [...SLACK_BASE, 'channel' => 'C1', 'text' => 'x', 'reply_broadcast' => true], []);

    Http::assertSent(fn ($request) => ! isset($request['reply_broadcast']));
});

it('surfaces a slack error code from a new node', function () {
    Http::fake(['slack.com/api/*' => Http::response(['ok' => false, 'error' => 'message_not_found'])]);

    app(SlackDeleteMessageNode::class)->execute(Run::factory()->create(), [...SLACK_BASE, 'channel' => 'C1', 'ts' => '9.9'], []);
})->throws(RuntimeException::class, 'Slack API error [chat.delete]: message_not_found');
