<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramSendVoiceNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_send_voice';
    }

    public function name(): string
    {
        return 'Telegram: Send Voice Message';
    }

    public function description(): string
    {
        return 'Sends an OGG (Opus), MP3 or M4A file as a voice message.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'voice'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'voice' => Field::text('Voice message', 'A link to an OGG (Opus), MP3 or M4A file or the file id of one Telegram already has.', 'https://example.com/file'),
                'caption' => Field::textarea('Caption', 'Up to 1024 characters.'),
                ...$this->sendFields(buttons: true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $message = $this->call($run, $config, 'sendVoice', [
            'chat_id' => $chat,
            'voice' => $this->requiredString($config, 'voice', 'The voice message'),
            'caption' => $this->caption($config),
            ...$this->sendOptions($config),
        ]);

        return $this->shapeMessage((array) $message);
    }
}
