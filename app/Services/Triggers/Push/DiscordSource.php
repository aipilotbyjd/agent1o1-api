<?php

namespace App\Services\Triggers\Push;

use App\Contracts\SupportsTriggerMode;
use App\Contracts\Triggers\ItemPushSource;
use App\Exceptions\ConnectorException;
use App\Exceptions\WatchRefusedException;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Nodes\Integrations\Discord\DiscordInteraction;
use App\Services\Connectors\ConnectorTokens;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Discord slash command and button triggers over the bot's Interactions URL.
 *
 * Discord pushes message, member and thread events only over a gateway
 * connection that this app doesn't keep, so those triggers poll. Interactions
 * are different: Discord posts them to one URL per application, which the app
 * sets itself (`PATCH /applications/@me`) with the bot's own token. Discord checks
 * the address with signed PINGs before it takes it, so the watch row is saved
 * first (the ingress finds the application's public key on it) and removed if
 * Discord refuses.
 *
 * An application has room for one Interactions URL: one that already points
 * somewhere else is refused rather than quietly taken over. The connection must
 * hold the application id and public key (Developer Portal > General Information).
 */
class DiscordSource extends AbstractWatchedSource implements ItemPushSource
{
    private const string API = 'https://discord.com/api/v10';

    public function __construct(
        ConnectorTokens $tokens,
        private readonly NodeRegistry $registry,
    ) {
        parent::__construct($tokens);
    }

    public function provider(): string
    {
        return 'discord';
    }

    protected function nodeTypes(): array
    {
        return ['discord_new_slash_command', 'discord_new_button_click'];
    }

    /**
     * Both interaction triggers of a bot share its one URL.
     */
    public function watchKeyFor(Trigger $trigger): ?string
    {
        return 'interactions';
    }

    /**
     * Point the application's Interactions URL at us, unless it points elsewhere.
     *
     * @throws WatchRefusedException when the connection lacks the id or key, the URL is taken, or Discord refuses it
     */
    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $credential = $this->credentialOf($watch);
        $token = $this->tokens->accessToken($credential);
        [$application, $publicKey] = $this->identity((array) $credential->data);

        $app = $this->api($token, 'GET', '/applications/@me');

        if ((string) ($app['id'] ?? '') !== $application) {
            throw new WatchRefusedException('The Application ID on the Discord connection does not belong to this bot\'s token. Check both in the Developer Portal.');
        }

        $current = (string) ($app['interactions_endpoint_url'] ?? '');

        if ($current !== '' && ! $this->isOurs($current)) {
            throw new WatchRefusedException('This Discord application already sends its interactions to another address ('.(parse_url($current, PHP_URL_HOST) ?: 'unknown').'), and it can have only one. Clear it in the Developer Portal to use slash command and button triggers here.');
        }

        $taken = ProviderWatch::query()->where('provider', $this->provider())->where('external_id', $application)
            ->when($watch->exists, fn ($query) => $query->whereKeyNot($watch->getKey()))->exists();

        if ($taken) {
            throw new WatchRefusedException('This Discord application is already used for interaction triggers through another connected account.');
        }

        // Discord PINGs the address before it accepts it, and the ingress reads the key from the watch.
        $existed = $watch->exists;
        $watch->forceFill(['external_id' => $application, 'secret' => $publicKey])->save();

        try {
            $this->api($token, 'PATCH', '/applications/@me', ['interactions_endpoint_url' => $this->url($application)]);
        } catch (Throwable $exception) {
            $existed ? $watch->forceFill(['external_id' => null])->save() : $watch->delete();

            throw $exception;
        }

        $watch->meta = [...(array) $watch->meta, 'application_id' => $application];
        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * Discord never expires the URL, but someone may have cleared or replaced it.
     *
     * @throws ConnectorException when it now points elsewhere
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $credential = $this->credentialOf($watch);
        $token = $this->tokens->accessToken($credential);
        [$application, $publicKey] = $this->identity((array) $credential->data);
        $current = (string) ($this->api($token, 'GET', '/applications/@me')['interactions_endpoint_url'] ?? '');

        if ($current !== '' && ! $this->isOurs($current)) {
            throw new ConnectorException('This Discord application\'s Interactions URL now points to another address, so slash command and button triggers stopped receiving. Clear it in the Developer Portal to start them again.');
        }

        // The key may have been corrected on the connection since.
        $watch->secret = $publicKey;

        if ($current === '') {
            $watch->forceFill(['external_id' => $application, 'secret' => $publicKey])->save();
            $this->api($token, 'PATCH', '/applications/@me', ['interactions_endpoint_url' => $this->url($application)]);
        }

        $watch->renew_at = now()->addDay();
    }

    /**
     * Clear the URL, if it is still ours.
     */
    public function deleteWatch(ProviderWatch $watch): void
    {
        $token = $this->tokens->accessToken($this->credentialOf($watch));

        if ($this->isOurs((string) ($this->api($token, 'GET', '/applications/@me')['interactions_endpoint_url'] ?? ''))) {
            $this->api($token, 'PATCH', '/applications/@me', ['interactions_endpoint_url' => null]);
        }
    }

    /**
     * The application's id: every delivery names it in the URL.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        return $watch?->external_id;
    }

    /**
     * Whether the interaction is this trigger's kind, for its command or button.
     */
    public function accepts(Trigger $trigger, array $item): bool
    {
        $node = $this->registry->resolve((string) ($trigger->config['node_type'] ?? ''));

        return $node instanceof SupportsTriggerMode
            && $node->triggerItems(['items' => [$item]], $this->nodeConfig($trigger)) !== [];
    }

    /**
     * The interaction as a flat item.
     */
    public function itemFor(array $event): array
    {
        return DiscordInteraction::item($event) ?? [];
    }

    public function itemId(array $item): string
    {
        return (string) ($item['interaction_id'] ?? '');
    }

    /**
     * The application id and public key a connection holds, checked.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string}
     *
     * @throws WatchRefusedException
     */
    private function identity(array $data): array
    {
        $application = trim((string) ($data['application_id'] ?? ''));
        $publicKey = strtolower(trim((string) ($data['public_key'] ?? '')));

        if ($application === '' || $publicKey === '') {
            throw new WatchRefusedException('Add the Application ID and Public key to the Discord connection (Developer Portal > General Information).');
        }

        if (preg_match('/^\d{15,25}$/', $application) !== 1 || preg_match('/^[0-9a-f]{64}$/', $publicKey) !== 1) {
            throw new WatchRefusedException('The Application ID or Public key on the Discord connection is not valid. Copy both again from the Developer Portal.');
        }

        return [$application, $publicKey];
    }

    private function url(string $application): string
    {
        return $this->callbackUrl().'?app='.$application;
    }

    private function isOurs(string $url): bool
    {
        return $url !== '' && str_starts_with($url, $this->callbackUrl());
    }

    /**
     * One Discord API call as the bot. A null in the body is sent (it clears a setting).
     *
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws ConnectorException when Discord refuses the token
     * @throws WatchRefusedException when it refuses the request in words
     */
    private function api(string $token, string $method, string $path, ?array $body = null): array
    {
        try {
            $response = Http::withHeaders(['Authorization' => 'Bot '.$token, 'User-Agent' => 'DiscordBot ('.config('app.url').', 1.0)'])->acceptJson()->timeout(20)
                ->send($method, self::API.$path, $body === null ? [] : ['json' => $body]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Discord could not be reached: '.$exception->getMessage());
        }

        if ($response->status() === 401) {
            throw new ConnectorException('Discord refused the bot token. Reconnect it.');
        }

        if ($response->successful()) {
            return (array) ($response->json() ?? []);
        }

        $message = (string) ($response->json('message') ?? "HTTP {$response->status()}");

        // A 4xx is Discord's answer to what we asked and repeats; a 429 or 5xx is worth another try.
        throw $response->status() >= 400 && $response->status() < 500 && $response->status() !== 429
            ? new WatchRefusedException("Discord said: {$message}".(str_contains(strtolower($message), 'verif') ? ' Check that the Public key is right and that this app is reachable over HTTPS.' : ''))
            : new RuntimeException("Discord error: {$message}.");
    }
}
