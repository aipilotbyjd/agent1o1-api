<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramSendAnimationNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_send_animation';
    }

    public function name(): string
    {
        return 'Telegram: Send Animation';
    }

    public function description(): string
    {
        return 'Sends a GIF or a silent MP4 that plays in a loop.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'animation'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'animation' => Field::text('Animation', 'A link to a GIF or silent MP4 or the file id of one Telegram already has.', 'https://example.com/file'),
                'caption' => Field::textarea('Caption', 'Up to 1024 characters.'),
                ...$this->sendFields(buttons: true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $message = $this->call($run, $config, 'sendAnimation', [
            'chat_id' => $chat,
            'animation' => $this->requiredString($config, 'animation', 'The animation'),
            'caption' => $this->caption($config),
            ...$this->sendOptions($config),
        ]);

        return $this->shapeMessage((array) $message);
    }
}
