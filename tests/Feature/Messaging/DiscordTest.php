<?php

use App\Enums\Triggers\SubscriptionStatus;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Discord\AbstractDiscordNode;
use App\Nodes\Integrations\Discord\DiscordAddReactionNode;
use App\Nodes\Integrations\Discord\DiscordBanMemberNode;
use App\Nodes\Integrations\Discord\DiscordBulkDeleteMessagesNode;
use App\Nodes\Integrations\Discord\DiscordCreateChannelNode;
use App\Nodes\Integrations\Discord\DiscordCreateInviteNode;
use App\Nodes\Integrations\Discord\DiscordCreateThreadNode;
use App\Nodes\Integrations\Discord\DiscordDeleteCommandNode;
use App\Nodes\Integrations\Discord\DiscordEditMessageNode;
use App\Nodes\Integrations\Discord\DiscordInteraction;
use App\Nodes\Integrations\Discord\DiscordListMessagesNode;
use App\Nodes\Integrations\Discord\DiscordNewButtonClickNode;
use App\Nodes\Integrations\Discord\DiscordNewSlashCommandNode;
use App\Nodes\Integrations\Discord\DiscordRegisterCommandNode;
use App\Nodes\Integrations\Discord\DiscordRespondToInteractionNode;
use App\Nodes\Integrations\Discord\DiscordSendDirectMessageNode;
use App\Nodes\Integrations\Discord\DiscordSendMessageNode;
use App\Nodes\Integrations\Discord\DiscordSendWebhookMessageNode;
use App\Nodes\Integrations\Discord\DiscordSetNicknameNode;
use App\Nodes\Integrations\Discord\DiscordTimeoutMemberNode;
use App\Services\Triggers\Push\DiscordSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\DiscordOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const DISCORD_GUILD = '111111111111111111';
const DISCORD_CHANNEL = '222222222222222222';
const DISCORD_USER = '333333333333333333';
const DISCORD_APP = '444444444444444444';

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function discordConfig(array $config = []): array
{
    return ['access_token' => 'bot_test', ...$config];
}

/**
 * Answer Discord's API. Each key is "METHOD /path" (without the version); the
 * value is the JSON body, a closure taking the request, or a ready response. A
 * route nobody answers is a 404, so a node that calls something unexpected
 * fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeDiscord(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['discord.com/api/v10/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.substr((string) parse_url($request->url(), PHP_URL_PATH), strlen('/api/v10'));

        if (! array_key_exists($key, $routes)) {
            return Http::response(['message' => "Nothing fakes {$key}", 'code' => 0], 404);
        }

        $answer = value($routes[$key], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : ($answer === null ? Http::response(null, 204) : Http::response($answer));
    }]);
}

/**
 * The JSON body sent to one route.
 *
 * @return array<string, mixed>
 */
function discordSent(string $route): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.substr((string) parse_url($pair[0]->url(), PHP_URL_PATH), strlen('/api/v10')) === $route));

    expect($found)->toHaveCount(1);

    return (array) $found[0][0]->data();
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function discordMessage(array $extra = []): array
{
    return [
        'id' => '555555555555555555', 'channel_id' => DISCORD_CHANNEL, 'guild_id' => DISCORD_GUILD, 'content' => 'hello', 'timestamp' => '2026-03-01T10:00:00+00:00',
        'author' => ['id' => DISCORD_USER, 'username' => 'ada', 'global_name' => 'Ada L', 'bot' => false], 'pinned' => false, 'type' => 0, ...$extra,
    ];
}

describe('discord nodes', function () {
    it('registers the nodes under the discord connector, and only the bot ones need an account', function () {
        $registry = app(NodeRegistry::class);
        $nodes = array_filter($registry->catalog(), fn (array $node): bool => str_starts_with($node['type'], 'discord_'));

        expect(count($nodes))->toBeGreaterThanOrEqual(56);

        foreach ($nodes as $node) {
            expect($node['category'])->toBe('discord')
                ->and($node['requires_connector'])->toBe($node['type'] !== 'discord_send_webhook_message');
        }

        expect($registry->resolve('discord_send_message'))->toBeInstanceOf(AbstractDiscordNode::class);
    });

    it('posts as the bot with the right headers, an embed, buttons, a reply and safe pings', function () {
        fakeDiscord(['POST /channels/'.DISCORD_CHANNEL.'/messages' => discordMessage()]);

        $output = (new DiscordSendMessageNode)->execute(Run::factory()->create(), discordConfig([
            'channel_id' => '<#'.DISCORD_CHANNEL.'>', 'content' => 'Deploy done', 'embed_title' => 'Release 2.4', 'embed_color' => '#57F287',
            'embed_fields' => '[{"name": "Status", "value": "OK", "inline": true}]', 'reply_to_message_id' => '666666666666666666', 'silent' => true,
            'buttons' => [['label' => 'Docs', 'url' => 'https://docs.test'], ['label' => 'Approve', 'custom_id' => 'approve:7', 'style' => 'success']],
        ]), []);

        expect($output)->toMatchArray(['id' => '555555555555555555', 'channel_id' => DISCORD_CHANNEL, 'author_name' => 'Ada L', 'content' => 'hello', 'url' => 'https://discord.com/channels/'.DISCORD_GUILD.'/'.DISCORD_CHANNEL.'/555555555555555555']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bot bot_test') && str_starts_with((string) $request->header('User-Agent')[0], 'DiscordBot ('));
        expect(discordSent('POST /channels/'.DISCORD_CHANNEL.'/messages'))->toBe([
            'content' => 'Deploy done',
            'embeds' => [['title' => 'Release 2.4', 'color' => 0x57F287, 'fields' => [['name' => 'Status', 'value' => 'OK', 'inline' => true]]]],
            'components' => [['type' => 1, 'components' => [
                ['type' => 2, 'style' => 5, 'label' => 'Docs', 'url' => 'https://docs.test'],
                ['type' => 2, 'style' => 3, 'label' => 'Approve', 'custom_id' => 'approve:7'],
            ]]],
            'flags' => 4096,
            'allowed_mentions' => ['parse' => ['users']],
            'message_reference' => ['message_id' => '666666666666666666', 'fail_if_not_exists' => false],
        ]);
    });

    it('checks what it is given before calling Discord', function () {
        fakeDiscord([]);
        $node = new DiscordSendMessageNode;
        $run = Run::factory()->create();

        expect(fn () => $node->execute($run, discordConfig(['channel_id' => 'general', 'content' => 'x']), []))->toThrow(InvalidArgumentException::class, 'is not a Discord id')
            ->and(fn () => $node->execute($run, discordConfig(['channel_id' => DISCORD_CHANNEL]), []))->toThrow(InvalidArgumentException::class, 'needs some text or an embed')
            ->and(fn () => $node->execute($run, discordConfig(['channel_id' => DISCORD_CHANNEL, 'content' => str_repeat('x', 2001)]), []))->toThrow(InvalidArgumentException::class, 'limit of 2000')
            ->and(fn () => $node->execute($run, discordConfig(['channel_id' => DISCORD_CHANNEL, 'embed_title' => 'x', 'embed_color' => 'teal']), []))->toThrow(InvalidArgumentException::class, 'not a colour')
            ->and(fn () => $node->execute($run, discordConfig(['channel_id' => DISCORD_CHANNEL, 'content' => 'x', 'buttons' => [['label' => 'Go', 'style' => 'huge']]]), []))->toThrow(InvalidArgumentException::class, 'not a button style');
        Http::assertNothingSent();
    });

    it('turns a refusal into an error that says what Discord said and what to do', function () {
        fakeDiscord(['POST /channels/'.DISCORD_CHANNEL.'/messages' => fn () => Http::response(['message' => 'Missing Access', 'code' => 50001], 403)]);

        expect(fn () => (new DiscordSendMessageNode)->execute(Run::factory()->create(), discordConfig(['channel_id' => DISCORD_CHANNEL, 'content' => 'x']), []))
            ->toThrow(RuntimeException::class, 'Discord error: Missing Access (code 50001). Give the bot that permission');
    });

    it('waits out a short rate limit once, then carries on', function () {
        $calls = 0;
        fakeDiscord(['POST /channels/'.DISCORD_CHANNEL.'/messages' => function () use (&$calls) {
            return ++$calls === 1 ? Http::response(['message' => 'You are being rate limited.', 'retry_after' => 0.01], 429) : Http::response(discordMessage());
        }]);

        $output = (new DiscordSendMessageNode)->execute(Run::factory()->create(), discordConfig(['channel_id' => DISCORD_CHANNEL, 'content' => 'x']), []);

        expect($calls)->toBe(2)->and($output['id'])->toBe('555555555555555555');
    });

    it('sends a direct message by opening the conversation first', function () {
        fakeDiscord([
            'POST /users/@me/channels' => ['id' => '777777777777777777'],
            'POST /channels/777777777777777777/messages' => discordMessage(['channel_id' => '777777777777777777', 'guild_id' => null]),
        ]);

        $output = (new DiscordSendDirectMessageNode)->execute(Run::factory()->create(), discordConfig(['user_id' => '<@!'.DISCORD_USER.'>', 'content' => 'Hi']), []);

        expect(discordSent('POST /users/@me/channels'))->toBe(['recipient_id' => DISCORD_USER])
            ->and($output)->toMatchArray(['dm_channel_id' => '777777777777777777', 'user_id' => DISCORD_USER]);
    });

    it('edits only what is sent: no reply reference, and an empty text is allowed with an embed', function () {
        fakeDiscord(['PATCH /channels/'.DISCORD_CHANNEL.'/messages/555555555555555555' => discordMessage()]);

        (new DiscordEditMessageNode)->execute(Run::factory()->create(), discordConfig(['channel_id' => DISCORD_CHANNEL, 'message_id' => '555555555555555555', 'embed_title' => 'Updated', 'tts' => true]), []);

        expect(discordSent('PATCH /channels/'.DISCORD_CHANNEL.'/messages/555555555555555555'))->toBe(['embeds' => [['title' => 'Updated']], 'allowed_mentions' => ['parse' => ['users']]]);
    });

    it('bulk deletes two to a hundred messages and says no to anything else', function () {
        fakeDiscord(['POST /channels/'.DISCORD_CHANNEL.'/messages/bulk-delete' => null]);
        $node = new DiscordBulkDeleteMessagesNode;
        $run = Run::factory()->create();

        $output = $node->execute($run, discordConfig(['channel_id' => DISCORD_CHANNEL, 'message_ids' => ['555555555555555551', '555555555555555552', '555555555555555551']]), []);

        expect($output['count'])->toBe(2)
            ->and(discordSent('POST /channels/'.DISCORD_CHANNEL.'/messages/bulk-delete'))->toBe(['messages' => ['555555555555555551', '555555555555555552']])
            ->and(fn () => $node->execute($run, discordConfig(['channel_id' => DISCORD_CHANNEL, 'message_ids' => ['555555555555555551']]), []))->toThrow(InvalidArgumentException::class, 'between 2 and 100');
    });

    it('lists messages newest first and can leave out bots', function () {
        fakeDiscord(['GET /channels/'.DISCORD_CHANNEL.'/messages' => [
            discordMessage(['id' => '555555555555555553']),
            discordMessage(['id' => '555555555555555552', 'author' => ['id' => DISCORD_USER, 'username' => 'bot', 'bot' => true]]),
        ]]);

        $output = (new DiscordListMessagesNode)->execute(Run::factory()->create(), discordConfig(['channel_id' => DISCORD_CHANNEL, 'include_bots' => false, 'limit' => 20, 'after' => '555555555555555000']), []);

        expect($output['count'])->toBe(1)->and($output['messages'][0]['id'])->toBe('555555555555555553');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'limit=20') && str_contains($request->url(), 'after=555555555555555000'));
    });

    it('encodes an emoji for the reaction URL', function () {
        fakeDiscord(['PUT /channels/'.DISCORD_CHANNEL.'/messages/555555555555555555/reactions/%F0%9F%91%8D/@me' => null]);

        $output = (new DiscordAddReactionNode)->execute(Run::factory()->create(), discordConfig(['channel_id' => DISCORD_CHANNEL, 'message_id' => '555555555555555555', 'emoji' => '👍']), []);

        expect($output['reacted'])->toBeTrue();
    });

    it('sends the nulls that clear a nickname and a timeout, with the audit-log reason', function () {
        fakeDiscord(['PATCH /guilds/'.DISCORD_GUILD.'/members/'.DISCORD_USER => ['user' => ['id' => DISCORD_USER, 'username' => 'ada'], 'roles' => []]]);
        $run = Run::factory()->create();

        (new DiscordSetNicknameNode)->execute($run, discordConfig(['guild' => DISCORD_GUILD, 'user_id' => DISCORD_USER, 'nickname' => '', 'reason' => 'Ünicode & spaces']), []);
        expect(discordSent('PATCH /guilds/'.DISCORD_GUILD.'/members/'.DISCORD_USER))->toBe(['nick' => null]);
        Http::assertSent(fn (Request $request): bool => $request->header('X-Audit-Log-Reason') === [rawurlencode('Ünicode & spaces')]);

        fakeDiscord(['PATCH /guilds/'.DISCORD_GUILD.'/members/'.DISCORD_USER => ['user' => ['id' => DISCORD_USER, 'username' => 'ada'], 'roles' => []]]);
        (new DiscordTimeoutMemberNode)->execute($run, discordConfig(['guild' => DISCORD_GUILD, 'user_id' => DISCORD_USER, 'minutes' => 0]), []);
        expect(discordSent('PATCH /guilds/'.DISCORD_GUILD.'/members/'.DISCORD_USER))->toBe(['communication_disabled_until' => null]);

        fakeDiscord(['PATCH /guilds/'.DISCORD_GUILD.'/members/'.DISCORD_USER => ['user' => ['id' => DISCORD_USER, 'username' => 'ada'], 'roles' => []]]);
        (new DiscordTimeoutMemberNode)->execute($run, discordConfig(['guild' => DISCORD_GUILD, 'user_id' => DISCORD_USER, 'minutes' => 30]), []);
        expect(discordSent('PATCH /guilds/'.DISCORD_GUILD.'/members/'.DISCORD_USER)['communication_disabled_until'])->toBeString()
            ->and(fn () => (new DiscordTimeoutMemberNode)->execute($run, discordConfig(['guild' => DISCORD_GUILD, 'user_id' => DISCORD_USER, 'minutes' => 99999]), []))->toThrow(InvalidArgumentException::class, '28 days');
    });

    it('creates channels, threads, invites and bans with Discord\'s own numbers', function () {
        $run = Run::factory()->create();

        fakeDiscord(['POST /guilds/'.DISCORD_GUILD.'/channels' => ['id' => DISCORD_CHANNEL, 'name' => 'news', 'type' => 5, 'guild_id' => DISCORD_GUILD]]);
        $channel = (new DiscordCreateChannelNode)->execute($run, discordConfig(['guild' => DISCORD_GUILD, 'name' => 'news', 'channel_type' => 'announcement', 'slowmode_seconds' => 0]), []);
        expect($channel['type'])->toBe('announcement')->and(discordSent('POST /guilds/'.DISCORD_GUILD.'/channels'))->toBe(['name' => 'news', 'type' => 5]);

        fakeDiscord(['POST /channels/'.DISCORD_CHANNEL.'/invites' => ['code' => 'abc123', 'max_uses' => 5]]);
        $invite = (new DiscordCreateInviteNode)->execute($run, discordConfig(['channel_id' => DISCORD_CHANNEL, 'expires_in_hours' => 2, 'max_uses' => 5]), []);
        expect($invite['url'])->toBe('https://discord.gg/abc123')
            ->and(discordSent('POST /channels/'.DISCORD_CHANNEL.'/invites'))->toBe(['max_age' => 7200, 'max_uses' => 5, 'temporary' => false, 'unique' => true]);

        fakeDiscord(['PUT /guilds/'.DISCORD_GUILD.'/bans/'.DISCORD_USER => null]);
        (new DiscordBanMemberNode)->execute($run, discordConfig(['guild' => DISCORD_GUILD, 'user_id' => DISCORD_USER, 'delete_days' => 2]), []);
        expect(discordSent('PUT /guilds/'.DISCORD_GUILD.'/bans/'.DISCORD_USER))->toBe(['delete_message_seconds' => 172800])
            ->and(fn () => (new DiscordBanMemberNode)->execute($run, discordConfig(['guild' => DISCORD_GUILD, 'user_id' => DISCORD_USER, 'delete_days' => 9]), []))->toThrow(InvalidArgumentException::class, '0 to 7');
    });

    it('starts a thread from a message, on its own, and as a forum post', function () {
        $run = Run::factory()->create();
        $thread = ['id' => '888888888888888888', 'name' => 'Talk', 'type' => 11, 'parent_id' => DISCORD_CHANNEL];

        fakeDiscord(['POST /channels/'.DISCORD_CHANNEL.'/messages/555555555555555555/threads' => $thread]);
        (new DiscordCreateThreadNode)->execute($run, discordConfig(['channel_id' => DISCORD_CHANNEL, 'name' => 'Talk', 'message_id' => '555555555555555555']), []);
        expect(discordSent('POST /channels/'.DISCORD_CHANNEL.'/messages/555555555555555555/threads'))->toBe(['name' => 'Talk', 'auto_archive_duration' => 1440]);

        fakeDiscord(['GET /channels/'.DISCORD_CHANNEL => ['id' => DISCORD_CHANNEL, 'type' => 0], 'POST /channels/'.DISCORD_CHANNEL.'/threads' => $thread, 'POST /channels/888888888888888888/messages' => discordMessage()]);
        (new DiscordCreateThreadNode)->execute($run, discordConfig(['channel_id' => DISCORD_CHANNEL, 'name' => 'Talk', 'content' => 'Start here', 'thread_type' => 'private']), []);
        expect(discordSent('POST /channels/'.DISCORD_CHANNEL.'/threads'))->toBe(['name' => 'Talk', 'auto_archive_duration' => 1440, 'type' => 12])
            ->and(discordSent('POST /channels/888888888888888888/messages')['content'])->toBe('Start here');

        fakeDiscord(['GET /channels/'.DISCORD_CHANNEL => ['id' => DISCORD_CHANNEL, 'type' => 15], 'POST /channels/'.DISCORD_CHANNEL.'/threads' => [...$thread, 'type' => 11]]);
        (new DiscordCreateThreadNode)->execute($run, discordConfig(['channel_id' => DISCORD_CHANNEL, 'name' => 'Idea', 'content' => 'Post body']), []);
        expect(discordSent('POST /channels/'.DISCORD_CHANNEL.'/threads')['message']['content'])->toBe('Post body');
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/888888888888888888/messages'));

        fakeDiscord(['GET /channels/'.DISCORD_CHANNEL => ['id' => DISCORD_CHANNEL, 'type' => 15]]);
        expect(fn () => (new DiscordCreateThreadNode)->execute($run, discordConfig(['channel_id' => DISCORD_CHANNEL, 'name' => 'Idea']), []))->toThrow(InvalidArgumentException::class, 'forum post needs a first message');
    });

    it('registers a slash command with typed options, required first, and finds one to delete by name', function () {
        $run = Run::factory()->create();

        fakeDiscord(['POST /applications/'.DISCORD_APP.'/guilds/'.DISCORD_GUILD.'/commands' => ['id' => '999999999999999999', 'name' => 'ticket', 'description' => 'Open a ticket']]);
        $command = (new DiscordRegisterCommandNode)->execute($run, discordConfig(['application_id' => DISCORD_APP, 'guild' => DISCORD_GUILD, 'name' => 'Ticket', 'description' => 'Open a ticket', 'options' => [
            ['name' => 'priority', 'description' => 'How urgent', 'type' => 'string', 'choices' => ['low', 'high']],
            ['name' => 'topic', 'description' => 'What about', 'type' => 'string', 'required' => true],
        ]]), []);

        expect($command)->toMatchArray(['id' => '999999999999999999', 'option_count' => 2])
            ->and(discordSent('POST /applications/'.DISCORD_APP.'/guilds/'.DISCORD_GUILD.'/commands'))->toBe([
                'name' => 'ticket', 'description' => 'Open a ticket', 'type' => 1,
                'options' => [
                    ['name' => 'topic', 'description' => 'What about', 'type' => 3, 'required' => true],
                    ['name' => 'priority', 'description' => 'How urgent', 'type' => 3, 'choices' => [['name' => 'low', 'value' => 'low'], ['name' => 'high', 'value' => 'high']]],
                ],
            ])
            ->and(fn () => (new DiscordRegisterCommandNode)->execute($run, discordConfig(['application_id' => DISCORD_APP, 'name' => 'bad name', 'description' => 'x']), []))->toThrow(InvalidArgumentException::class, '1 to 32')
            ->and(fn () => (new DiscordRegisterCommandNode)->execute($run, discordConfig(['name' => 'ok', 'description' => 'x']), []))->toThrow(InvalidArgumentException::class, 'Application ID');

        fakeDiscord(['GET /applications/'.DISCORD_APP.'/commands' => [['id' => '999999999999999999', 'name' => 'ticket']], 'DELETE /applications/'.DISCORD_APP.'/commands/999999999999999999' => null]);
        $deleted = (new DiscordDeleteCommandNode)->execute($run, discordConfig(['application_id' => DISCORD_APP, 'command' => '/Ticket']), []);
        expect($deleted)->toBe(['deleted' => true, 'command_id' => '999999999999999999']);
    });

    it('answers an interaction through its token, by filling in the first reply or sending another, using the connection\'s application id', function () {
        $run = Run::factory()->create();
        ConnectorCredential::factory()->create([
            'workspace_id' => $run->workspace_id,
            'connector_id' => (Connector::query()->firstWhere('key', 'discord') ?? Connector::factory()->create(['key' => 'discord']))->id,
            'data' => ['api_key' => 'bot_test', 'application_id' => DISCORD_APP, 'public_key' => str_repeat('a', 64)],
            'is_default' => true,
        ]);

        fakeDiscord(['PATCH /webhooks/'.DISCORD_APP.'/tok-1/messages/@original' => discordMessage()]);
        (new DiscordRespondToInteractionNode)->execute($run, ['interaction_token' => 'tok-1', 'content' => 'Done', 'silent' => true], []);
        expect(discordSent('PATCH /webhooks/'.DISCORD_APP.'/tok-1/messages/@original'))->toBe(['content' => 'Done', 'allowed_mentions' => ['parse' => ['users']]]);

        fakeDiscord(['POST /webhooks/'.DISCORD_APP.'/tok-1' => discordMessage()]);
        (new DiscordRespondToInteractionNode)->execute($run, ['interaction_token' => 'tok-1', 'mode' => 'new_message', 'ephemeral' => true, 'content' => 'Just you'], []);
        expect(discordSent('POST /webhooks/'.DISCORD_APP.'/tok-1'))->toMatchArray(['content' => 'Just you', 'flags' => 64]);
    });

    it('posts through a webhook URL, but only to Discord\'s own host', function () {
        Http::swap(new Factory);
        Http::fake(['discord.com/api/webhooks/*' => Http::response(['id' => '1', 'channel_id' => DISCORD_CHANNEL, 'content' => 'Ping', 'timestamp' => 'now'])]);
        $node = new DiscordSendWebhookMessageNode;
        $run = Run::factory()->create();

        $output = $node->execute($run, ['webhook_url' => 'https://discord.com/api/webhooks/123456789012345678/abc-DEF_1', 'content' => 'Ping', 'username' => 'Deploy bot', 'buttons' => [['label' => 'x', 'url' => 'https://a.test']]], []);

        expect($output['id'])->toBe('1');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '?wait=true') && $request['username'] === 'Deploy bot' && ! isset($request['components']));
        expect(fn () => $node->execute($run, ['webhook_url' => 'https://evil.test/api/webhooks/123456789012345678/abc', 'content' => 'x'], []))->toThrow(InvalidArgumentException::class, 'not a Discord webhook URL');
    });
});

describe('discord dropdowns', function () {
    it('lists the bot\'s servers, their channels without categories, and a server\'s roles', function () {
        fakeDiscord([
            'GET /users/@me/guilds' => [['id' => DISCORD_GUILD, 'name' => 'Acme', 'approximate_member_count' => 42]],
            'GET /guilds/'.DISCORD_GUILD.'/channels' => [['id' => DISCORD_CHANNEL, 'name' => 'general', 'type' => 0, 'position' => 1], ['id' => '1', 'name' => 'Text', 'type' => 4, 'position' => 0]],
            'GET /guilds/'.DISCORD_GUILD.'/roles' => [['id' => '10', 'name' => 'Admin', 'position' => 2], ['id' => '11', 'name' => 'Bot', 'position' => 3, 'managed' => true], ['id' => '12', 'name' => 'Member', 'position' => 1]],
        ]);
        $query = fn (array $config = [], ?string $search = null): NodeOptionsQuery => new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), $config, 'bot_test', $search);
        $source = app(DiscordOptions::class);

        expect($source->load('discord.guilds', $query())->toArray()['options'])->toBe([['value' => DISCORD_GUILD, 'label' => 'Acme', 'description' => '42 members']])
            ->and($source->load('discord.channels', $query())->toArray()['options'])->toBe([['value' => DISCORD_CHANNEL, 'label' => '#general', 'description' => 'Acme']])
            ->and($source->load('discord.channels', $query(search: 'nope'))->options)->toBe([])
            ->and(array_column($source->load('discord.roles', $query(['guild' => DISCORD_GUILD]))->toArray()['options'], 'label'))->toBe(['Admin', 'Member'])
            ->and(fn () => $source->load('discord.roles', $query()))->toThrow(App\Exceptions\ConnectorException::class, 'Choose a server first');
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bot bot_test'));
    });
});

describe('discord interactions', function () {
    it('reads a slash command with its subcommand and options, and a button with its id', function () {
        $command = DiscordInteraction::item(['id' => '1100000000000000000', 'type' => 2, 'token' => 't', 'application_id' => DISCORD_APP, 'guild_id' => DISCORD_GUILD, 'channel_id' => DISCORD_CHANNEL,
            'member' => ['nick' => 'Adaaa', 'roles' => ['5'], 'user' => ['id' => DISCORD_USER, 'username' => 'ada']],
            'data' => ['id' => '9', 'name' => 'Ticket', 'options' => [['type' => 1, 'name' => 'open', 'options' => [['name' => 'topic', 'type' => 3, 'value' => 'Billing'], ['name' => 'urgent', 'type' => 5, 'value' => true]]]]]]);
        $button = DiscordInteraction::item(['id' => '1100000000000000001', 'type' => 3, 'token' => 't2', 'application_id' => DISCORD_APP, 'channel_id' => DISCORD_CHANNEL, 'user' => ['id' => DISCORD_USER, 'username' => 'ada'], 'data' => ['custom_id' => 'approve:7', 'values' => ['a']], 'message' => ['id' => '5', 'content' => 'Approve?']]);

        expect($command)->toMatchArray(['kind' => 'slash_command', 'command' => 'ticket', 'subcommand' => 'open', 'options' => ['topic' => 'Billing', 'urgent' => true], 'user_id' => DISCORD_USER, 'display_name' => 'Adaaa', 'role_ids' => ['5'], 'interaction_token' => 't'])
            ->and($button)->toMatchArray(['kind' => 'button_click', 'custom_id' => 'approve:7', 'values' => ['a'], 'message_id' => '5', 'message_content' => 'Approve?', 'guild_id' => null])
            ->and(DiscordInteraction::item(['id' => '1', 'type' => 4]))->toBeNull();
    });

    it('lets the slash command and button triggers through only for their command, button and server', function () {
        $slash = new DiscordNewSlashCommandNode;
        $button = new DiscordNewButtonClickNode;
        $ticket = ['kind' => 'slash_command', 'command' => 'ticket', 'guild_id' => DISCORD_GUILD, 'interaction_id' => '7'];
        $click = ['kind' => 'button_click', 'custom_id' => 'approve:7', 'channel_id' => DISCORD_CHANNEL, 'guild_id' => DISCORD_GUILD, 'interaction_id' => '8'];
        $passes = fn ($node, array $item, array $config = []): bool => $node->triggerItems(['items' => [$item]], $config) !== [];

        expect($passes($slash, $ticket))->toBeTrue()
            ->and($passes($slash, $ticket, ['command' => '/Ticket']))->toBeTrue()
            ->and($passes($slash, $ticket, ['command' => 'other']))->toBeFalse()
            ->and($passes($slash, $ticket, ['guild' => '999']))->toBeFalse()
            ->and($passes($slash, $click))->toBeFalse()
            ->and($passes($button, $click, ['custom_id' => 'approve:*']))->toBeTrue()
            ->and($passes($button, $click, ['custom_id' => 'reject:*']))->toBeFalse()
            ->and($passes($button, $click, ['custom_id' => 'approve:7', 'channel_id' => '1']))->toBeFalse()
            ->and($slash->triggerItemId($ticket))->toBe('7');
    });
});

/**
 * A published workflow whose first step is a Discord trigger on a bot, with the
 * subscription synced against a faked Discord.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 * @param  array<string, mixed>  $credentialData
 */
function discordTrigger(string $nodeType = 'discord_new_slash_command', array $nodeConfig = [], array $routes = [], ?array $credentialData = null, ?string $publicKey = null): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    $app = ['id' => DISCORD_APP, 'interactions_endpoint_url' => null];
    fakeDiscord(['GET /applications/@me' => $app, 'PATCH /applications/@me' => $app, ...$routes]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'discord') ?? Connector::factory()->create(['key' => 'discord']))
        ->create(['data' => $credentialData ?? ['api_key' => 'bot_test', 'application_id' => DISCORD_APP, 'public_key' => $publicKey ?? str_repeat('ab', 32)], 'created_by' => $owner->id]);

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
 * Discord's request to the Interactions URL, signed with the application's key.
 *
 * @param  array<string, mixed>  $body
 */
function discordDelivery(array $body, string $secretKey, ?string $app = DISCORD_APP, ?int $at = null, bool $tamper = false)
{
    $json = json_encode($body);
    $timestamp = (string) ($at ?? time());
    $signature = bin2hex(sodium_crypto_sign_detached($timestamp.$json, $secretKey));

    return test()->call('POST', '/api/hooks/apps/discord'.($app === null ? '' : "?app={$app}"), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE_ED25519' => $signature,
        'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
    ], $tamper ? $json.' ' : $json);
}

describe('discord instant delivery', function () {
    beforeEach(function () {
        if (! function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('The sodium extension is needed to sign Discord interactions.');
        }

        $pair = sodium_crypto_sign_keypair();
        $this->discordSecret = sodium_crypto_sign_secretkey($pair);
        $this->discordPublic = bin2hex(sodium_crypto_sign_publickey($pair));
    });

    it('sets the Interactions URL once, on the application\'s watch, with the public key kept for the ingress', function () {
        $trigger = discordTrigger(publicKey: $this->discordPublic);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('interactions')
            ->and($watch->external_id)->toBe(DISCORD_APP)
            ->and($watch->secret)->toBe($this->discordPublic)
            ->and($trigger->subscription->routing_key)->toBe(DISCORD_APP)
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active);
        expect(discordSent('PATCH /applications/@me'))->toBe(['interactions_endpoint_url' => url('/api/hooks/apps/discord').'?app='.DISCORD_APP]);
    });

    it('answers Discord\'s PING with a PONG and acknowledges a command with "thinking…", starting a run once', function () {
        $trigger = discordTrigger(nodeConfig: ['command' => 'ticket'], publicKey: $this->discordPublic);
        $command = ['id' => '1100000000000000000', 'type' => 2, 'token' => 'tok', 'application_id' => DISCORD_APP, 'guild_id' => DISCORD_GUILD, 'channel_id' => DISCORD_CHANNEL, 'member' => ['user' => ['id' => DISCORD_USER, 'username' => 'ada']], 'data' => ['name' => 'ticket', 'options' => [['name' => 'topic', 'type' => 3, 'value' => 'Billing']]]];

        discordDelivery(['type' => 1], $this->discordSecret)->assertOk()->assertExactJson(['type' => 1]);
        discordDelivery($command, $this->discordSecret)->assertOk()->assertExactJson(['type' => 5]);
        discordDelivery($command, $this->discordSecret)->assertOk()->assertExactJson(['type' => 5]);
        discordDelivery([...$command, 'id' => '1100000000000000001', 'data' => ['name' => 'other']], $this->discordSecret)->assertOk();

        $events = $trigger->events()->get();

        expect($events)->toHaveCount(1)
            ->and($events->first()->payload)->toMatchArray(['kind' => 'slash_command', 'command' => 'ticket', 'options' => ['topic' => 'Billing'], 'interaction_token' => 'tok']);
    });

    it('defers a button press as an update of its own message', function () {
        $trigger = discordTrigger('discord_new_button_click', ['custom_id' => 'approve:*'], publicKey: $this->discordPublic);

        discordDelivery(['id' => '1100000000000000002', 'type' => 3, 'token' => 'tok', 'application_id' => DISCORD_APP, 'channel_id' => DISCORD_CHANNEL, 'user' => ['id' => DISCORD_USER, 'username' => 'ada'], 'data' => ['custom_id' => 'approve:7']], $this->discordSecret)
            ->assertOk()->assertExactJson(['type' => 6]);

        expect($trigger->events()->get()->pluck('payload.custom_id')->all())->toBe(['approve:7']);
    });

    it('refuses a request that is badly signed, stale, altered or for another application', function () {
        $trigger = discordTrigger(publicKey: $this->discordPublic);
        $other = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
        $command = ['id' => '1100000000000000003', 'type' => 2, 'application_id' => DISCORD_APP, 'data' => ['name' => 'ticket']];

        discordDelivery($command, $other)->assertUnauthorized();
        discordDelivery($command, $this->discordSecret, at: time() - 3600)->assertUnauthorized();
        discordDelivery($command, $this->discordSecret, tamper: true)->assertUnauthorized();
        discordDelivery($command, $this->discordSecret, app: '999')->assertUnauthorized();
        discordDelivery($command, $this->discordSecret, app: null)->assertUnauthorized();
        test()->postJson('/api/hooks/apps/discord?app='.DISCORD_APP, $command)->assertUnauthorized();

        expect($trigger->events()->count())->toBe(0);
    });

    it('is closed while the switch is off', function () {
        discordTrigger(publicKey: $this->discordPublic);
        config(['triggers.push.discord.enabled' => false]);

        discordDelivery(['type' => 1], $this->discordSecret)->assertNotFound();
    });

    it('will not take over an application whose Interactions URL points elsewhere', function () {
        $trigger = discordTrigger(routes: ['GET /applications/@me' => ['id' => DISCORD_APP, 'interactions_endpoint_url' => 'https://other-app.test/discord']]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)
            ->and($trigger->subscription->last_error)->toContain('other-app.test')->toContain('only one')
            ->and(ProviderWatch::query()->count())->toBe(0);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PATCH');
    });

    it('says what is missing when the connection has no application id or public key, or the id is the wrong one', function () {
        $missing = discordTrigger(credentialData: ['api_key' => 'bot_test']);
        expect($missing->subscription->status)->toBe(SubscriptionStatus::Failed)->and($missing->subscription->last_error)->toContain('Application ID and Public key');

        $badKey = discordTrigger(credentialData: ['api_key' => 'bot_test', 'application_id' => DISCORD_APP, 'public_key' => 'short']);
        expect($badKey->subscription->last_error)->toContain('not valid');

        $wrongApp = discordTrigger(credentialData: ['api_key' => 'bot_test', 'application_id' => '555555555555555550', 'public_key' => str_repeat('ab', 32)]);
        expect($wrongApp->subscription->last_error)->toContain('does not belong to this bot');
    });

    it('drops the watch again when Discord cannot verify the address', function () {
        $trigger = discordTrigger(routes: ['PATCH /applications/@me' => fn () => Http::response(['message' => 'The specified interactions endpoint url could not be verified.', 'code' => 50240], 400)]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)
            ->and($trigger->subscription->last_error)->toContain('could not be verified')->toContain('Public key')
            ->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('sets the URL again when it was cleared, says so when it was replaced, and clears it only if still ours', function () {
        discordTrigger(publicKey: $this->discordPublic);
        $watch = ProviderWatch::query()->sole();
        $source = app(DiscordSource::class);
        $ours = url('/api/hooks/apps/discord').'?app='.DISCORD_APP;

        fakeDiscord(['GET /applications/@me' => ['id' => DISCORD_APP, 'interactions_endpoint_url' => null], 'PATCH /applications/@me' => ['id' => DISCORD_APP]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect(discordSent('PATCH /applications/@me'))->toBe(['interactions_endpoint_url' => $ours])
            ->and($watch->fresh()->status)->toBe(SubscriptionStatus::Active);

        fakeDiscord(['GET /applications/@me' => ['id' => DISCORD_APP, 'interactions_endpoint_url' => 'https://other-app.test/discord']]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->status)->toBe(SubscriptionStatus::ReconnectNeeded)->and($watch->fresh()->last_error)->toContain('now points to another address');

        fakeDiscord(['GET /applications/@me' => ['id' => DISCORD_APP, 'interactions_endpoint_url' => $ours], 'PATCH /applications/@me' => ['id' => DISCORD_APP]]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(discordSent('PATCH /applications/@me'))->toBe(['interactions_endpoint_url' => null])
            ->and(ProviderWatch::query()->count())->toBe(0);
    });
});

describe('discord polling triggers', function () {
    it('polls a channel: a baseline first, then one run for each new message, oldest first', function () {
        Queue::fake([FireTriggerEvent::class]);

        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'discord') ?? Connector::factory()->create(['key' => 'discord']))
            ->create(['data' => ['api_key' => 'bot_test'], 'created_by' => $owner->id]);

        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'discord_list_new_messages', 'config' => ['credential_id' => $credential->id, 'activate' => true, 'channel_id' => DISCORD_CHANNEL]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);

        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        $poll = fn (array $messages) => fakeDiscord(['GET /channels/'.DISCORD_CHANNEL.'/messages' => $messages]);

        // Newest first, as Discord lists them.
        $poll([discordMessage(['id' => '555555555555555501'])]);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll([
            discordMessage(['id' => '555555555555555503']),
            discordMessage(['id' => '555555555555555502', 'author' => ['id' => '1', 'username' => 'bot', 'bot' => true]]),
            discordMessage(['id' => '555555555555555501']),
        ]);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        // The bot's own message is left out by default.
        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->all())->toBe(['555555555555555503']);
    });
});
