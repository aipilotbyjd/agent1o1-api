<?php

use App\Enums\Triggers\SubscriptionStatus;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\RouteProviderEvent;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Telegram\AbstractTelegramNode;
use App\Nodes\Integrations\Telegram\TelegramAnswerCallbackQueryNode;
use App\Nodes\Integrations\Telegram\TelegramBanChatMemberNode;
use App\Nodes\Integrations\Telegram\TelegramDeleteMessagesNode;
use App\Nodes\Integrations\Telegram\TelegramGetFileNode;
use App\Nodes\Integrations\Telegram\TelegramNewMessageNode;
use App\Nodes\Integrations\Telegram\TelegramSendMediaGroupNode;
use App\Nodes\Integrations\Telegram\TelegramSendMessageNode;
use App\Nodes\Integrations\Telegram\TelegramSendPollNode;
use App\Nodes\Integrations\Telegram\TelegramSetMyCommandsNode;
use App\Nodes\Integrations\Telegram\TelegramUpdate;
use App\Services\Triggers\Push\TelegramSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Triggers\TriggerStatus;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const TELEGRAM_TOKEN = '123456:ABC-token';

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function telegramConfig(array $config = []): array
{
    return ['access_token' => TELEGRAM_TOKEN, ...$config];
}

/**
 * Answer the Bot API: each key is a method name; the value is its `result`, a
 * closure taking the request, or a ready response. A method nobody answers is
 * an error, so a node that calls something unexpected fails the test.
 *
 * @param  array<string, mixed>  $methods
 */
function fakeTelegram(array $methods): void
{
    Http::swap(new Factory);
    Http::fake(['api.telegram.org/*' => function (Request $request) use ($methods) {
        $method = basename((string) parse_url($request->url(), PHP_URL_PATH));

        if (! array_key_exists($method, $methods)) {
            return Http::response(['ok' => false, 'error_code' => 400, 'description' => "Nothing fakes {$method}"], 400);
        }

        $answer = value($methods[$method], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : Http::response(['ok' => true, 'result' => $answer]);
    }]);
}

/**
 * The JSON body sent to one Bot API method.
 *
 * @return array<string, mixed>
 */
function telegramSent(string $method): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => str_ends_with((string) parse_url($pair[0]->url(), PHP_URL_PATH), '/'.$method)));

    expect($found)->toHaveCount(1);

    return (array) $found[0][0]->data();
}

/**
 * @return array<string, mixed>
 */
function telegramMessage(array $extra = []): array
{
    return [
        'message_id' => 7, 'date' => 1700000000, 'chat' => ['id' => -1001, 'type' => 'supergroup', 'title' => 'Team', 'username' => 'teamchat'],
        'from' => ['id' => 42, 'is_bot' => false, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'username' => 'ada'], 'text' => 'hello', ...$extra,
    ];
}

describe('telegram nodes', function () {
    it('registers the nodes under the telegram connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'telegram_'));

        expect(count($types))->toBeGreaterThanOrEqual(55);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractTelegramNode::class)
                ->and($registry->resolve($type)->category())->toBe('telegram');
        }
    });

    it('sends a message with the bot token in the URL and shapes what comes back', function () {
        fakeTelegram(['sendMessage' => telegramMessage()]);

        $output = (new TelegramSendMessageNode)->execute(Run::factory()->create(), telegramConfig(['chat_id' => '-1001', 'text' => 'hello', 'parse_mode' => 'HTML', 'silent' => true, 'reply_to_message_id' => 3, 'disable_link_preview' => true]), []);

        expect($output)->toMatchArray(['message_id' => 7, 'chat_id' => -1001, 'chat_type' => 'supergroup', 'from_username' => 'ada', 'from_name' => 'Ada Lovelace', 'text' => 'hello', 'link' => 'https://t.me/teamchat/7', 'date' => '2023-11-14T22:13:20+00:00']);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.telegram.org/bot'.TELEGRAM_TOKEN.'/sendMessage'));
        expect(telegramSent('sendMessage'))->toBe([
            'chat_id' => -1001, 'text' => 'hello', 'link_preview_options' => ['is_disabled' => true], 'parse_mode' => 'HTML',
            'disable_notification' => true, 'reply_parameters' => ['message_id' => 3, 'allow_sending_without_reply' => true],
        ]);
    });

    it('accepts @username chats and refuses anything else, with the sentence that fixes it', function () {
        fakeTelegram(['sendMessage' => telegramMessage()]);
        $node = new TelegramSendMessageNode;

        $node->execute(Run::factory()->create(), telegramConfig(['chat_id' => '@teamchat', 'text' => 'hi']), []);

        expect(telegramSent('sendMessage')['chat_id'])->toBe('@teamchat')
            ->and(fn () => $node->execute(Run::factory()->create(), telegramConfig(['chat_id' => 'Ada', 'text' => 'hi']), []))->toThrow(InvalidArgumentException::class, 'is not a Telegram chat')
            ->and(fn () => $node->execute(Run::factory()->create(), telegramConfig(['chat_id' => '1', 'text' => '']), []))->toThrow(InvalidArgumentException::class, 'message is required')
            ->and(fn () => $node->execute(Run::factory()->create(), telegramConfig(['chat_id' => '1', 'text' => str_repeat('x', 4097)]), []))->toThrow(InvalidArgumentException::class, 'over Telegram\'s limit of 4096')
            ->and(fn () => $node->execute(Run::factory()->create(), telegramConfig(['chat_id' => '1', 'text' => 'x', 'parse_mode' => 'Wiki']), []))->toThrow(InvalidArgumentException::class, 'not a Telegram format');
    });

    it('turns inline buttons, rows of buttons and a reply keyboard into Telegram\'s markup', function () {
        fakeTelegram(['sendMessage' => telegramMessage()]);
        $node = new TelegramSendMessageNode;
        $run = Run::factory()->create();

        $node->execute($run, telegramConfig(['chat_id' => '1', 'text' => 'Pick', 'buttons' => [['text' => 'Open', 'url' => 'https://acme.test'], ['text' => 'Yes', 'callback_data' => 'yes:1']]]), []);
        expect(telegramSent('sendMessage')['reply_markup'])->toBe(['inline_keyboard' => [[['text' => 'Open', 'url' => 'https://acme.test'], ['text' => 'Yes', 'callback_data' => 'yes:1']]]]);

        fakeTelegram(['sendMessage' => telegramMessage()]);
        $node->execute($run, telegramConfig(['chat_id' => '1', 'text' => 'Pick', 'buttons' => '[[{"text":"A"}],[{"text":"B","callback_data":"b"}]]']), []);
        expect(telegramSent('sendMessage')['reply_markup'])->toBe(['inline_keyboard' => [[['text' => 'A', 'callback_data' => 'A']], [['text' => 'B', 'callback_data' => 'b']]]]);

        fakeTelegram(['sendMessage' => telegramMessage()]);
        $node->execute($run, telegramConfig(['chat_id' => '1', 'text' => 'Pick', 'keyboard' => ['Yes', 'No'], 'one_time_keyboard' => true]), []);
        expect(telegramSent('sendMessage')['reply_markup'])->toBe(['keyboard' => [[['text' => 'Yes'], ['text' => 'No']]], 'resize_keyboard' => true, 'one_time_keyboard' => true]);

        expect(fn () => $node->execute($run, telegramConfig(['chat_id' => '1', 'text' => 'x', 'buttons' => [['text' => 'Big', 'callback_data' => str_repeat('d', 65)]]]), []))->toThrow(InvalidArgumentException::class, '64 bytes');
    });

    it('turns Telegram\'s refusal into an error that says what it said', function () {
        fakeTelegram(['sendMessage' => fn () => Http::response(['ok' => false, 'error_code' => 429, 'description' => 'Too Many Requests: retry after 5', 'parameters' => ['retry_after' => 5]], 429)]);

        expect(fn () => (new TelegramSendMessageNode)->execute(Run::factory()->create(), telegramConfig(['chat_id' => '1', 'text' => 'x']), []))
            ->toThrow(RuntimeException::class, 'Telegram error 429: Too Many Requests: retry after 5 (try again in 5s)');
    });

    it('sends an album of two to ten files, captioned on the first', function () {
        fakeTelegram(['sendMediaGroup' => [telegramMessage(['message_id' => 1]), telegramMessage(['message_id' => 2])]]);
        $node = new TelegramSendMediaGroupNode;

        $output = $node->execute(Run::factory()->create(), telegramConfig(['chat_id' => '1', 'media' => ['https://x.test/a.jpg', 'https://x.test/b.jpg'], 'caption' => 'Trip']), []);

        expect($output['count'])->toBe(2)
            ->and(telegramSent('sendMediaGroup')['media'])->toBe([['type' => 'photo', 'media' => 'https://x.test/a.jpg', 'caption' => 'Trip'], ['type' => 'photo', 'media' => 'https://x.test/b.jpg']])
            ->and(fn () => $node->execute(Run::factory()->create(), telegramConfig(['chat_id' => '1', 'media' => ['https://x.test/a.jpg']]), []))->toThrow(InvalidArgumentException::class, 'between 2 and 10');
    });

    it('sends a poll or a quiz, and checks the quiz has a right answer', function () {
        fakeTelegram(['sendPoll' => telegramMessage(['poll' => ['id' => 'poll-9', 'question' => 'Lunch?']])]);
        $node = new TelegramSendPollNode;

        $output = $node->execute(Run::factory()->create(), telegramConfig(['chat_id' => '1', 'question' => 'Lunch?', 'options' => ['Pizza', 'Sushi'], 'poll_type' => 'quiz', 'correct_option' => 2, 'is_anonymous' => false]), []);

        expect($output['poll_id'])->toBe('poll-9')
            ->and(telegramSent('sendPoll'))->toMatchArray(['type' => 'quiz', 'correct_option_id' => 1, 'is_anonymous' => false, 'options' => [['text' => 'Pizza'], ['text' => 'Sushi']]])
            ->and(fn () => $node->execute(Run::factory()->create(), telegramConfig(['chat_id' => '1', 'question' => 'Q', 'options' => ['A', 'B'], 'poll_type' => 'quiz']), []))->toThrow(InvalidArgumentException::class, 'right answer')
            ->and(fn () => $node->execute(Run::factory()->create(), telegramConfig(['chat_id' => '1', 'question' => 'Q', 'options' => ['A']]), []))->toThrow(InvalidArgumentException::class, 'between 2 and 10');
    });

    it('answers a button press, bans with an optional time, and deletes in bulk', function () {
        fakeTelegram(['answerCallbackQuery' => true, 'banChatMember' => true, 'deleteMessages' => true]);
        $run = Run::factory()->create();

        (new TelegramAnswerCallbackQueryNode)->execute($run, telegramConfig(['callback_query_id' => 'cb1', 'text' => 'Done', 'show_alert' => true]), []);
        (new TelegramBanChatMemberNode)->execute($run, telegramConfig(['chat_id' => '-100', 'user_id' => 42, 'ban_hours' => 24, 'revoke_messages' => true]), []);
        $deleted = (new TelegramDeleteMessagesNode)->execute($run, telegramConfig(['chat_id' => '-100', 'message_ids' => ['3', '4', '4', 'x']]), []);

        expect(telegramSent('answerCallbackQuery'))->toBe(['callback_query_id' => 'cb1', 'text' => 'Done', 'show_alert' => true])
            ->and(telegramSent('banChatMember'))->toMatchArray(['chat_id' => -100, 'user_id' => 42, 'revoke_messages' => true])
            ->and(telegramSent('banChatMember')['until_date'])->toBeGreaterThan(now()->addHours(23)->getTimestamp())
            ->and($deleted)->toMatchArray(['deleted' => true, 'message_ids' => [3, 4], 'count' => 2]);
    });

    it('only puts the bot token in a download link when asked', function () {
        fakeTelegram(['getFile' => ['file_id' => 'f1', 'file_size' => 10, 'file_path' => 'photos/a.jpg']]);
        $run = Run::factory()->create();

        $without = (new TelegramGetFileNode)->execute($run, telegramConfig(['file_id' => 'f1']), []);
        $with = (new TelegramGetFileNode)->execute($run, telegramConfig(['file_id' => 'f1', 'include_download_url' => true]), []);

        expect($without['download_url'])->toBeNull()
            ->and($with['download_url'])->toBe('https://api.telegram.org/file/bot'.TELEGRAM_TOKEN.'/photos/a.jpg');
    });

    it('validates bot commands before sending them', function () {
        fakeTelegram(['setMyCommands' => true]);
        $node = new TelegramSetMyCommandsNode;

        $node->execute(Run::factory()->create(), telegramConfig(['commands' => '[{"command": "/Start", "description": "Say hello"}]']), []);

        expect(telegramSent('setMyCommands')['commands'])->toBe([['command' => 'start', 'description' => 'Say hello']])
            ->and(fn () => $node->execute(Run::factory()->create(), telegramConfig(['commands' => [['command' => 'bad command', 'description' => 'x']]]), []))->toThrow(InvalidArgumentException::class, 'needs a name');
    });
});

describe('telegram updates', function () {
    it('reads a message, its command, its file and who sent it', function () {
        $item = TelegramUpdate::item(['update_id' => 10, 'message' => telegramMessage(['text' => '/Start@acme_bot  welcome home', 'entities' => [['type' => 'bot_command', 'offset' => 0, 'length' => 15]], 'photo' => [['file_id' => 'small', 'file_size' => 1], ['file_id' => 'large', 'file_size' => 9]]])]);

        expect($item)->toMatchArray([
            'update_id' => 10, 'kind' => 'message', 'chat_id' => -1001, 'from_id' => 42, 'from_username' => 'ada', 'is_bot' => false,
            'command' => 'start', 'command_arguments' => 'welcome home', 'media_type' => 'photo', 'file_id' => 'large',
        ]);
    });

    it('tells the other kinds of update apart', function () {
        $joined = TelegramUpdate::item(['update_id' => 1, 'message' => telegramMessage(['text' => null, 'new_chat_members' => [['id' => 5, 'first_name' => 'New', 'username' => 'newbie']]])]);
        $left = TelegramUpdate::item(['update_id' => 2, 'message' => telegramMessage(['text' => null, 'left_chat_member' => ['id' => 6, 'first_name' => 'Gone']])]);
        $edited = TelegramUpdate::item(['update_id' => 3, 'edited_message' => telegramMessage(['edit_date' => 1700000100])]);
        $post = TelegramUpdate::item(['update_id' => 4, 'channel_post' => telegramMessage(['chat' => ['id' => -1002, 'type' => 'channel', 'title' => 'News']])]);
        $callback = TelegramUpdate::item(['update_id' => 5, 'callback_query' => ['id' => 'cb1', 'data' => 'approve:9', 'from' => ['id' => 42, 'first_name' => 'Ada'], 'message' => telegramMessage()]]);
        $vote = TelegramUpdate::item(['update_id' => 6, 'poll_answer' => ['poll_id' => 'p1', 'option_ids' => [1], 'user' => ['id' => 42, 'first_name' => 'Ada']]]);
        $status = TelegramUpdate::item(['update_id' => 7, 'my_chat_member' => ['chat' => ['id' => -1001, 'type' => 'group', 'title' => 'Team'], 'from' => ['id' => 42, 'first_name' => 'Ada'], 'date' => 1700000000, 'old_chat_member' => ['status' => 'left'], 'new_chat_member' => ['status' => 'member']]]);
        $request = TelegramUpdate::item(['update_id' => 8, 'chat_join_request' => ['chat' => ['id' => -1001, 'type' => 'supergroup', 'title' => 'Team'], 'from' => ['id' => 42, 'first_name' => 'Ada'], 'date' => 1700000000, 'bio' => 'hi']]);

        expect($joined)->toMatchArray(['kind' => 'member_joined', 'new_members' => [['from_id' => 5, 'from_username' => 'newbie', 'from_name' => 'New', 'from_language' => null, 'is_bot' => false]]])
            ->and($left['kind'])->toBe('member_left')
            ->and($edited)->toMatchArray(['kind' => 'edited_message', 'edit_date' => '2023-11-14T22:15:00+00:00'])
            ->and($post)->toMatchArray(['kind' => 'channel_post', 'chat_type' => 'channel'])
            ->and($callback)->toMatchArray(['kind' => 'callback_query', 'callback_query_id' => 'cb1', 'data' => 'approve:9', 'message_id' => 7, 'chat_id' => -1001, 'from_id' => 42])
            ->and($vote)->toMatchArray(['kind' => 'poll_answer', 'poll_id' => 'p1', 'option_ids' => [1], 'from_id' => 42])
            ->and($status)->toMatchArray(['kind' => 'my_chat_member', 'added' => true, 'removed' => false, 'new_status' => 'member'])
            ->and($request)->toMatchArray(['kind' => 'chat_join_request', 'bio' => 'hi', 'from_id' => 42])
            ->and(TelegramUpdate::item(['update_id' => 9, 'inline_query' => ['id' => 'q']]))->toBeNull();
    });

    it('lets a new-message trigger through only for its chats, kind of chat, command and humans', function () {
        $node = new TelegramNewMessageNode;
        $message = TelegramUpdate::item(['update_id' => 1, 'message' => telegramMessage(['text' => '/help me', 'entities' => [['type' => 'bot_command', 'offset' => 0, 'length' => 5]]])]);
        $fromBot = [...$message, 'is_bot' => true];
        $joined = TelegramUpdate::item(['update_id' => 2, 'message' => telegramMessage(['text' => null, 'new_chat_members' => [['id' => 5, 'first_name' => 'N']]])]);
        $passes = fn (array $item, array $config = []): bool => $node->triggerItems(['items' => [$item]], $config) !== [];

        expect($passes($message))->toBeTrue()
            ->and($passes($message, ['chat_id' => '-1001, 55']))->toBeTrue()
            ->and($passes($message, ['chat_id' => '@TeamChat']))->toBeTrue()
            ->and($passes($message, ['chat_id' => '-999']))->toBeFalse()
            ->and($passes($message, ['chat_type' => 'private']))->toBeFalse()
            ->and($passes($message, ['chat_type' => 'supergroup']))->toBeTrue()
            ->and($passes($message, ['command' => '/help']))->toBeTrue()
            ->and($passes($message, ['command' => 'start']))->toBeFalse()
            ->and($passes($fromBot))->toBeFalse()
            ->and($passes($fromBot, ['ignore_bots' => false]))->toBeTrue()
            ->and($passes($joined))->toBeFalse()
            ->and($node->triggerItemId($message))->toBe('1');
    });
});

/**
 * A published workflow whose first step is a Telegram trigger on a bot, with
 * the subscription synced against a faked Telegram.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $methods  Bot API answers added to the defaults
 */
function telegramTrigger(string $nodeType = 'telegram_new_message', array $nodeConfig = [], array $methods = []): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeTelegram([
        'getWebhookInfo' => ['url' => ''],
        'setWebhook' => true,
        'deleteWebhook' => true,
        'getMe' => ['id' => 123456, 'username' => 'acme_bot', 'can_read_all_group_messages' => true],
        ...$methods,
    ]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'telegram') ?? Connector::factory()->create(['key' => 'telegram']))
        ->create(['data' => ['api_key' => TELEGRAM_TOKEN], 'created_by' => $owner->id]);

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
 * A delivery as Telegram sends it.
 *
 * @param  array<string, mixed>  $update
 */
function telegramDelivery(array $update, ?string $secret, string $bot = '123456')
{
    return test()->call('POST', "/api/hooks/apps/telegram?bot={$bot}", [], [], [], array_filter([
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => $secret,
    ]), json_encode($update));
}

describe('telegram instant delivery', function () {
    it('registers the bot\'s webhook once, with a secret and every update kind the triggers need', function () {
        $trigger = telegramTrigger();
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('bot')
            ->and($watch->external_id)->toBe('123456')
            ->and($watch->secret)->not->toBeEmpty()
            ->and($trigger->subscription->routing_key)->toBe('123456')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active);

        expect(telegramSent('setWebhook'))->toBe([
            'url' => url('/api/hooks/apps/telegram').'?bot=123456', 'secret_token' => $watch->secret,
            'allowed_updates' => TelegramUpdate::ALLOWED_UPDATES, 'max_connections' => 40,
        ]);
    });

    it('starts a run for each message that passes the trigger\'s settings, once', function () {
        $trigger = telegramTrigger(nodeConfig: ['chat_id' => '-1001']);
        $secret = (string) ProviderWatch::query()->sole()->secret;
        $update = ['update_id' => 500, 'message' => telegramMessage()];

        telegramDelivery($update, $secret)->assertOk();
        telegramDelivery($update, $secret)->assertOk();
        telegramDelivery(['update_id' => 501, 'message' => telegramMessage(['chat' => ['id' => -999, 'type' => 'group']])], $secret)->assertOk();

        $events = $trigger->events()->get();

        expect($events)->toHaveCount(1)
            ->and($events->first()->payload)->toMatchArray(['kind' => 'message', 'text' => 'hello', 'from_username' => 'ada', 'chat_id' => -1001]);
    });

    it('routes a button press to the button trigger, not the message trigger', function () {
        $trigger = telegramTrigger('telegram_new_callback_query', ['callback_data' => 'approve:*']);
        $secret = (string) ProviderWatch::query()->sole()->secret;

        telegramDelivery(['update_id' => 1, 'callback_query' => ['id' => 'cb1', 'data' => 'reject:3', 'from' => ['id' => 1, 'first_name' => 'A'], 'message' => telegramMessage()]], $secret)->assertOk();
        telegramDelivery(['update_id' => 2, 'callback_query' => ['id' => 'cb2', 'data' => 'approve:3', 'from' => ['id' => 1, 'first_name' => 'A'], 'message' => telegramMessage()]], $secret)->assertOk();
        telegramDelivery(['update_id' => 3, 'message' => telegramMessage()], $secret)->assertOk();

        expect($trigger->events()->get()->pluck('payload.data')->all())->toBe(['approve:3']);
    });

    it('refuses a delivery with a wrong, missing or another bot\'s secret', function () {
        $trigger = telegramTrigger();
        $secret = (string) ProviderWatch::query()->sole()->secret;
        $update = ['update_id' => 1, 'message' => telegramMessage()];

        telegramDelivery($update, 'not-the-secret')->assertUnauthorized();
        telegramDelivery($update, null)->assertUnauthorized();
        telegramDelivery($update, $secret, bot: '999')->assertUnauthorized();
        test()->postJson('/api/hooks/apps/telegram', $update)->assertUnauthorized();

        expect($trigger->events()->count())->toBe(0);
    });

    it('is closed while the switch is off', function () {
        telegramTrigger();
        $secret = (string) ProviderWatch::query()->sole()->secret;
        config(['triggers.push.telegram.enabled' => false]);

        telegramDelivery(['update_id' => 1, 'message' => telegramMessage()], $secret)->assertNotFound();
    });

    it('will not take over a bot that already sends its updates somewhere else', function () {
        $trigger = telegramTrigger(methods: ['getWebhookInfo' => ['url' => 'https://other-app.test/telegram']]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)
            ->and($trigger->subscription->last_error)->toContain('other-app.test')->toContain('only one')
            ->and(ProviderWatch::query()->count())->toBe(0);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/setWebhook'));
    });

    it('says so when Telegram refuses the address, and asks for a reconnect when it refuses the token', function () {
        $refused = telegramTrigger(methods: ['setWebhook' => fn () => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: bad webhook: An HTTPS URL must be provided for webhook'], 400)]);

        expect($refused->subscription->status)->toBe(SubscriptionStatus::Failed)
            ->and($refused->subscription->last_error)->toContain('HTTPS URL must be provided');

        $badToken = telegramTrigger(methods: ['getWebhookInfo' => fn () => Http::response(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401)]);

        expect($badToken->subscription->status)->toBe(SubscriptionStatus::ReconnectNeeded);
    });

    it('warns that privacy mode hides group messages from the bot', function () {
        $trigger = telegramTrigger(nodeConfig: ['chat_type' => 'supergroup'], methods: ['getMe' => ['id' => 123456, 'username' => 'acme_bot', 'can_read_all_group_messages' => false]]);

        expect($trigger->subscription->last_error)->toContain('privacy mode');
    });

    it('puts the webhook back when it was removed, and says so when it was taken', function () {
        telegramTrigger();
        $watch = ProviderWatch::query()->sole();
        $source = app(TelegramSource::class);

        fakeTelegram(['getWebhookInfo' => ['url' => ''], 'setWebhook' => true]);
        app(WatchManager::class)->renew($source, $watch->fresh());

        expect(telegramSent('setWebhook')['secret_token'])->toBe($watch->secret)
            ->and($watch->fresh()->status)->toBe(SubscriptionStatus::Active);

        fakeTelegram(['getWebhookInfo' => ['url' => 'https://other-app.test/hook']]);
        app(WatchManager::class)->renew($source, $watch->fresh());

        expect($watch->fresh()->status)->toBe(SubscriptionStatus::ReconnectNeeded)
            ->and($watch->fresh()->last_error)->toContain('now points to another address');
    });

    it('takes the webhook down when the watch goes, but only if it is still ours', function () {
        telegramTrigger();
        $watch = ProviderWatch::query()->sole();
        $source = app(TelegramSource::class);

        fakeTelegram(['getWebhookInfo' => ['url' => url('/api/hooks/apps/telegram').'?bot=123456'], 'deleteWebhook' => true]);
        app(WatchManager::class)->remove($source, $watch);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/deleteWebhook'));

        telegramTrigger();
        fakeTelegram(['getWebhookInfo' => ['url' => 'https://other-app.test/hook']]);
        app(WatchManager::class)->remove($source, ProviderWatch::query()->sole());
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/deleteWebhook'));
    });

    it('reports a trigger that needs instant delivery when none is working', function () {
        $trigger = telegramTrigger(methods: ['getWebhookInfo' => ['url' => 'https://other-app.test/telegram']]);

        expect(app(TriggerStatus::class)->for($trigger)['state'])->toBe('needs_push');
    });
});
