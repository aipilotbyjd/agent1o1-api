<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TelegramSetChatTitleNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_set_chat_title';
    }

    public function name(): string
    {
        return 'Telegram: Set Chat Title';
    }

    public function description(): string
    {
        return 'Renames a group or channel the bot is an admin of.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'title'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'title' => Field::text('New title', '1 to 128 characters.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $title = $this->requiredString($config, 'title', 'The title');

        if (mb_strlen($title) > 128) {
            throw new InvalidArgumentException('The title is over Telegram\'s limit of 128 characters.');
        }

        $this->call($run, $config, 'setChatTitle', ['chat_id' => $chat, 'title' => $title]);

        return ['chat_id' => $chat, 'title' => $title];
    }
}
