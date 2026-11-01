<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use App\Models\Triggers\ProviderWatch;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A Telegram bot's webhook. The URL names the bot (`?bot=<id>`), and Telegram
 * sends the secret registered for it in `X-Telegram-Bot-Api-Secret-Token` with
 * every update, so a request that doesn't carry that bot's secret is refused.
 */
class TelegramIngress implements ProviderIngress
{
    public function provider(): string
    {
        return 'telegram';
    }

    public function verify(Request $request): bool
    {
        $bot = (string) $request->query('bot');
        $sent = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($bot === '' || $sent === '') {
            return false;
        }

        $secret = ProviderWatch::query()->where('provider', 'telegram')->where('external_id', $bot)->first()?->secret;

        return is_string($secret) && hash_equals($secret, $sent);
    }

    /**
     * Telegram has no handshake: every request is an update.
     */
    public function handshake(Request $request): array|Response|null
    {
        return null;
    }

    public function events(Request $request): array
    {
        $update = $request->json()->all();
        $bot = (string) $request->query('bot');

        if (! is_numeric($update['update_id'] ?? null) || $bot === '') {
            return [];
        }

        $kind = array_values(array_diff(array_keys($update), ['update_id']))[0] ?? 'unknown';

        return [[
            'event_id' => $bot.':'.$update['update_id'],
            'event_type' => (string) $kind,
            'routing_key' => $bot,
            'payload' => $update,
        ]];
    }
}
