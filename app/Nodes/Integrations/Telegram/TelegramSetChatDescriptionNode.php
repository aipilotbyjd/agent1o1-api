<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TelegramSetChatDescriptionNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_set_chat_description';
    }

    public function name(): string
    {
        return 'Telegram: Set Chat Description';
    }

    public function description(): string
    {
        return 'Changes the description of a group or channel the bot is an admin of.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'description' => Field::textarea('New description', 'Up to 255 characters. Empty removes it.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $description = (string) ($config['description'] ?? '');

        if (mb_strlen($description) > 255) {
            throw new InvalidArgumentException('The description is over Telegram\'s limit of 255 characters.');
        }

        $this->call($run, $config, 'setChatDescription', ['chat_id' => $chat, 'description' => $description]);

        return ['chat_id' => $chat, 'description' => $description];
    }
}
