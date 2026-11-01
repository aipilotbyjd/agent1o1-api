<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use App\Contracts\Triggers\RepliesToDelivery;
use App\Models\Triggers\ProviderWatch;
use App\Nodes\Integrations\Discord\DiscordInteraction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A Discord bot's Interactions URL. The URL names the application
 * (`?app=<id>`); Discord signs every request with that application's Ed25519
 * key (`X-Signature-Ed25519` over the timestamp and the body), and the
 * matching public key is held on the application's watch. Discord also sends
 * signed PINGs, and bad signatures on purpose, when the URL is set: the ones
 * that fail must be refused or Discord won't accept the address.
 *
 * It answers at once and never waits on a flow: a slash command or a button is
 * acknowledged with "thinking…" (or "update later" for a button), and the flow's
 * own "Respond" step fills the reply in through the interaction token.
 */
class DiscordIngress implements ProviderIngress, RepliesToDelivery
{
    private const int RESPONSE_PONG = 1;

    private const int RESPONSE_DEFERRED_MESSAGE = 5;

    private const int RESPONSE_DEFERRED_UPDATE = 6;

    private const int RESPONSE_AUTOCOMPLETE = 8;

    public function provider(): string
    {
        return 'discord';
    }

    public function verify(Request $request): bool
    {
        $app = (string) $request->query('app');
        $signature = (string) $request->header('X-Signature-Ed25519');
        $timestamp = (string) $request->header('X-Signature-Timestamp');

        if ($app === '' || $signature === '' || $timestamp === '' || ! function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > (int) config('triggers.push.discord.max_age_seconds', 300)) {
            return false;
        }

        $key = ProviderWatch::query()->where('provider', 'discord')->where('external_id', $app)->first()?->secret;

        if (! is_string($key) || strlen($key) !== 64 || strlen($signature) !== 128 || ! ctype_xdigit($key) || ! ctype_xdigit($signature)) {
            return false;
        }

        return sodium_crypto_sign_verify_detached((string) hex2bin($signature), $timestamp.$request->getContent(), (string) hex2bin($key));
    }

    /**
     * Discord's PING, sent when the URL is set and now and then after.
     */
    public function handshake(Request $request): array|Response|null
    {
        return (int) $request->json('type') === DiscordInteraction::TYPE_PING ? ['type' => self::RESPONSE_PONG] : null;
    }

    public function events(Request $request): array
    {
        $interaction = $request->json()->all();
        $app = (string) $request->query('app');
        $type = (int) ($interaction['type'] ?? 0);

        if (! in_array($type, [DiscordInteraction::TYPE_COMMAND, DiscordInteraction::TYPE_COMPONENT], true) || ! filled($interaction['id'] ?? null) || (string) ($interaction['application_id'] ?? '') !== $app) {
            return [];
        }

        return [[
            'event_id' => (string) $interaction['id'],
            'event_type' => $type === DiscordInteraction::TYPE_COMMAND ? 'application_command' : 'message_component',
            'routing_key' => $app,
            'payload' => $interaction,
        ]];
    }

    /**
     * "Thinking…" for a command, "I'll update this message" for a button; nothing for the rest.
     */
    public function reply(Request $request): array
    {
        return match ((int) $request->json('type')) {
            DiscordInteraction::TYPE_COMPONENT, DiscordInteraction::TYPE_MODAL => ['type' => self::RESPONSE_DEFERRED_UPDATE],
            DiscordInteraction::TYPE_AUTOCOMPLETE => ['type' => self::RESPONSE_AUTOCOMPLETE, 'data' => ['choices' => []]],
            default => ['type' => self::RESPONSE_DEFERRED_MESSAGE],
        };
    }
}
