<?php

namespace App\Services\Triggers\Push;

use App\Contracts\SupportsTriggerMode;
use App\Contracts\Triggers\ItemPushSource;
use App\Exceptions\ConnectorException;
use App\Exceptions\WatchRefusedException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Nodes\Integrations\Telegram\TelegramUpdate;
use App\Services\Connectors\ConnectorTokens;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Telegram triggers over a bot's webhook.
 *
 * Telegram only ever sends a bot its updates; it never lists them, and a bot
 * that has a webhook can't also be polled. So there is no polling fallback:
 * these triggers work through the webhook or not at all (their nodes say so).
 *
 * One webhook per bot, shared by every Telegram trigger on that account. It is
 * registered with a secret that Telegram sends back in a header on every
 * delivery, and the bot's id in the URL tells us whose secret to check. A bot
 * has room for one webhook, so a bot that already sends its updates somewhere
 * else is refused rather than quietly taken over.
 *
 * The update itself is the item (`TelegramUpdate`), and the trigger node
 * decides whether its settings let it through, so the filters live in one place.
 */
class TelegramSource extends AbstractWatchedSource implements ItemPushSource
{
    private const string API = 'https://api.telegram.org';

    /**
     * Webhook deliveries Telegram may make at once.
     */
    private const int MAX_CONNECTIONS = 40;

    public function __construct(
        ConnectorTokens $tokens,
        private readonly NodeRegistry $registry,
    ) {
        parent::__construct($tokens);
    }

    public function provider(): string
    {
        return 'telegram';
    }

    protected function nodeTypes(): array
    {
        return [
            'telegram_new_message', 'telegram_edited_message', 'telegram_new_channel_post', 'telegram_new_callback_query',
            'telegram_member_joined', 'telegram_member_left', 'telegram_new_poll_answer', 'telegram_bot_chat_status',
            'telegram_new_join_request',
        ];
    }

    /**
     * Every Telegram trigger of an account shares its bot's one webhook.
     */
    public function watchKeyFor(Trigger $trigger): ?string
    {
        return 'bot';
    }

    /**
     * Register the bot's webhook, unless the bot already sends its updates elsewhere.
     *
     * @throws WatchRefusedException when the bot is taken or Telegram refuses the address
     */
    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $token = $this->tokens->accessToken($this->credentialOf($watch));
        $botId = $this->botId($token);

        $current = (string) ($this->api($token, 'getWebhookInfo')['url'] ?? '');

        if ($current !== '' && ! $this->isOurs($current)) {
            throw new WatchRefusedException('This bot already sends its updates to another address ('.(parse_url($current, PHP_URL_HOST) ?: 'unknown').'), and a bot can have only one. Remove that webhook or connect a different bot.');
        }

        // Another account here already holds this bot's webhook: replacing its secret would silence that one.
        $taken = ProviderWatch::query()->where('provider', $this->provider())->where('external_id', $botId)
            ->when($watch->exists, fn ($query) => $query->whereKeyNot($watch->getKey()))->exists();

        if ($taken) {
            throw new WatchRefusedException('This bot is already used for Telegram triggers through another connected account.');
        }

        $this->register($watch, $token, $botId, $this->newSecret());
    }

    /**
     * Telegram never expires a webhook, but someone may have removed or replaced
     * it: put ours back when it was removed, and say so when it was replaced.
     *
     * @throws ConnectorException when the bot's webhook now points elsewhere
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $token = $this->tokens->accessToken($this->credentialOf($watch));
        $current = (string) ($this->api($token, 'getWebhookInfo')['url'] ?? '');

        if ($current !== '' && ! $this->isOurs($current)) {
            throw new ConnectorException('This bot\'s Telegram webhook now points to another address, so its triggers stopped receiving updates. Remove that webhook to start them again.');
        }

        if ($current === '' || $watch->secret === null) {
            $this->register($watch, $token, $this->botId($token), $watch->secret ?? $this->newSecret());

            return;
        }

        $watch->renew_at = now()->addDay();
    }

    /**
     * Take the webhook down, if it is still ours.
     */
    public function deleteWatch(ProviderWatch $watch): void
    {
        $token = $this->tokens->accessToken($this->credentialOf($watch));

        if ($this->isOurs((string) ($this->api($token, 'getWebhookInfo')['url'] ?? ''))) {
            $this->api($token, 'deleteWebhook');
        }
    }

    /**
     * The bot's id: every delivery names it in the URL.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        return $watch?->external_id;
    }

    /**
     * Telegram's privacy mode hides most group messages from a bot.
     */
    public function accessWarning(Trigger $trigger): ?string
    {
        $config = $this->nodeConfig($trigger);
        $asksAboutGroups = ($trigger->config['node_type'] ?? null) === 'telegram_new_message'
            && (in_array($config['chat_type'] ?? null, ['group', 'supergroup'], true) || str_starts_with(trim((string) ($config['chat_id'] ?? '')), '-'));

        if (! $asksAboutGroups || filled($config['command'] ?? null)) {
            return null;
        }

        $credential = ConnectorCredential::query()->where('workspace_id', $trigger->workspace_id)->find($trigger->credential_id);

        if ($credential === null) {
            return null;
        }

        try {
            $bot = $this->api($this->tokens->accessToken($credential, markUsed: false), 'getMe');
        } catch (Throwable) {
            return null;
        }

        return ($bot['can_read_all_group_messages'] ?? true) === false
            ? 'In groups this bot only sees commands and replies to it. Turn off privacy mode in @BotFather (/setprivacy) to see every message.'
            : null;
    }

    /**
     * Whether the update is this trigger's kind of event, in a chat it watches.
     */
    public function accepts(Trigger $trigger, array $item): bool
    {
        $node = $this->registry->resolve((string) ($trigger->config['node_type'] ?? ''));

        return $node instanceof SupportsTriggerMode
            && $node->triggerItems(['items' => [$item]], $this->nodeConfig($trigger)) !== [];
    }

    /**
     * The update as a flat item (a message, a button press, a vote).
     */
    public function itemFor(array $event): array
    {
        return TelegramUpdate::item($event) ?? [];
    }

    /**
     * Telegram's own update number.
     */
    public function itemId(array $item): string
    {
        return (string) ($item['update_id'] ?? '');
    }

    /**
     * Point the bot's webhook at us and record it on the watch.
     */
    private function register(ProviderWatch $watch, string $token, string $botId, string $secret): void
    {
        $this->api($token, 'setWebhook', [
            'url' => $this->callbackUrl().'?bot='.$botId,
            'secret_token' => $secret,
            'allowed_updates' => TelegramUpdate::ALLOWED_UPDATES,
            'max_connections' => self::MAX_CONNECTIONS,
        ]);

        $watch->external_id = $botId;
        $watch->secret = $secret;
        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * Whether a webhook address is the one this app registers.
     */
    private function isOurs(string $url): bool
    {
        return $url !== '' && str_starts_with($url, $this->callbackUrl());
    }

    /**
     * A bot's id is the part of its token before the colon.
     *
     * @throws ConnectorException when the token isn't a bot token
     */
    private function botId(string $token): string
    {
        $id = explode(':', $token, 2)[0];

        return ctype_digit($id) ? $id : throw new ConnectorException('The Telegram bot token is not valid. Reconnect it.');
    }

    /**
     * One Bot API call.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws ConnectorException when Telegram refuses the token
     * @throws WatchRefusedException when it refuses the request in words
     */
    private function api(string $token, string $method, array $params = []): array
    {
        try {
            $request = Http::acceptJson()->asJson()->timeout(20);
            $response = $params === [] ? $request->get(self::API.'/bot'.$token.'/'.$method) : $request->post(self::API.'/bot'.$token.'/'.$method, $params);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Telegram could not be reached: '.$exception->getMessage());
        }

        if ($response->status() === 401 || $response->status() === 404) {
            throw new ConnectorException('Telegram refused the bot token. Reconnect it.');
        }

        if ($response->json('ok') === true) {
            return (array) ($response->json('result') ?? []);
        }

        $description = (string) ($response->json('description') ?? "HTTP {$response->status()}");

        // A 4xx is Telegram's answer to what we asked and repeats; a 429 or 5xx is worth another try.
        throw $response->status() >= 400 && $response->status() < 500 && $response->status() !== 429
            ? new WatchRefusedException("Telegram said: {$description}.")
            : new RuntimeException("Telegram error: {$description}.");
    }
}
