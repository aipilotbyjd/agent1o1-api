<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use Illuminate\Support\Carbon;

class TelegramGetWebhookInfoNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_get_webhook_info';
    }

    public function name(): string
    {
        return 'Telegram: Get Webhook Status';
    }

    public function description(): string
    {
        return 'Shows where Telegram sends the bot\'s updates, how many are waiting, and the last delivery error. Useful when a Telegram trigger is not firing.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $info = (array) $this->call($run, $config, 'getWebhookInfo');

        return [
            'url' => ($info['url'] ?? '') !== '' ? $info['url'] : null,
            'pending_update_count' => $info['pending_update_count'] ?? 0,
            'last_error_message' => $info['last_error_message'] ?? null,
            'last_error_at' => isset($info['last_error_date']) ? Carbon::createFromTimestampUTC((int) $info['last_error_date'])->toIso8601String() : null,
            'max_connections' => $info['max_connections'] ?? null,
            'allowed_updates' => $info['allowed_updates'] ?? [],
        ];
    }
}
