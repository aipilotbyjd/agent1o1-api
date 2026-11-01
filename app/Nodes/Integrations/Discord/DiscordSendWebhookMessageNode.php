<?php

namespace App\Nodes\Integrations\Discord;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Posts through a channel's webhook URL (Channel settings > Integrations >
 * Webhooks): no bot and no connected account, which is how most notifications
 * reach Discord. The URL is the credential, so it is only ever sent to Discord.
 */
class DiscordSendWebhookMessageNode implements DeclaresEffect, NodeContract
{
    use BuildsDiscordMessages;

    public function type(): string
    {
        return 'discord_send_webhook_message';
    }

    public function category(): string
    {
        return 'discord';
    }

    public function name(): string
    {
        return 'Discord: Send Webhook Message';
    }

    public function description(): string
    {
        return 'Posts a message to a channel through its webhook URL, with its own name and picture. No bot or connected account is needed.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['webhook_url'],
            'properties' => [
                'webhook_url' => Field::text('Webhook URL', 'From the channel\'s settings: Integrations > Webhooks > Copy Webhook URL. Anyone with it can post, so keep it secret.', 'https://discord.com/api/webhooks/123/abc'),
                'username' => Field::text('Name to post as', 'Overrides the webhook\'s name.'),
                'avatar_url' => Field::advanced(Field::text('Picture', 'A link to the picture to post with.')),
                'thread_id' => Field::advanced(Field::text('Thread', 'Post in this thread of the channel.')),
                // A webhook can't carry buttons: only an application's own messages can.
                ...array_diff_key($this->messageFields(reply: false), ['buttons' => true]),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $url = trim((string) ($config['webhook_url'] ?? ''));

        // Only Discord's own hosts: the URL is a secret and must not be pointed anywhere else.
        if (preg_match('~^https://(?:(?:ptb|canary)\.)?discord(?:app)?\.com/api/(?:v\d+/)?webhooks/\d{15,25}/[\w-]+$~', $url) !== 1) {
            throw new InvalidArgumentException('That is not a Discord webhook URL. Copy it from the channel\'s Integrations > Webhooks settings.');
        }

        $body = $this->messageBody($config);
        unset($body['message_reference'], $body['components']);

        if (filled($config['username'] ?? null)) {
            $body['username'] = mb_substr((string) $config['username'], 0, 80);
        }

        if (filled($config['avatar_url'] ?? null)) {
            $body['avatar_url'] = (string) $config['avatar_url'];
        }

        $thread = $this->id($config, 'thread_id', 'The thread', required: false);

        $response = Http::acceptJson()->asJson()->timeout(30)->post($url.'?wait=true'.($thread === null ? '' : "&thread_id={$thread}"), array_filter($body, fn (mixed $value): bool => $value !== null));

        if ($response->failed()) {
            throw new RuntimeException('Discord error: '.($response->json('message') ?? "HTTP {$response->status()}").($response->status() === 404 ? '. The webhook was deleted: create a new one.' : '.'));
        }

        return [
            'id' => $response->json('id'),
            'channel_id' => $response->json('channel_id'),
            'content' => $response->json('content'),
            'created_at' => $response->json('timestamp'),
            'webhook_id' => $response->json('webhook_id'),
        ];
    }
}
