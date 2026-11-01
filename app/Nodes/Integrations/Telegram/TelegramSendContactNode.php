<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramSendContactNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_send_contact';
    }

    public function name(): string
    {
        return 'Telegram: Send Contact';
    }

    public function description(): string
    {
        return 'Sends a phone contact card.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'phone_number', 'first_name'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'phone_number' => Field::text('Phone number', null, '+14155550123'),
                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                ...$this->sendFields(formatting: false, buttons: false),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);

        return $this->shapeMessage((array) $this->call($run, $config, 'sendContact', [
            'chat_id' => $chat,
            'phone_number' => $this->requiredString($config, 'phone_number', 'The phone number'),
            'first_name' => $this->requiredString($config, 'first_name', 'The first name'),
            'last_name' => filled($config['last_name'] ?? null) ? (string) $config['last_name'] : null,
            ...$this->sendOptions($config, formatting: false),
        ]));
    }
}
