<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramSendAudioNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_send_audio';
    }

    public function name(): string
    {
        return 'Telegram: Send Audio';
    }

    public function description(): string
    {
        return 'Sends an MP3 or M4A as a music track, with a caption, title and performer.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'audio'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'audio' => Field::text('Audio', 'A link to an MP3 or M4A file or the file id of one Telegram already has.', 'https://example.com/file'),
                'caption' => Field::textarea('Caption', 'Up to 1024 characters.'),
                'title' => Field::advanced(Field::text('Track title')),
                'performer' => Field::advanced(Field::text('Performer')),
                ...$this->sendFields(buttons: true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $message = $this->call($run, $config, 'sendAudio', [
            'chat_id' => $chat,
            'audio' => $this->requiredString($config, 'audio', 'The audio'),
            'caption' => $this->caption($config),
            'title' => filled($config['title'] ?? null) ? (string) $config['title'] : null,
            'performer' => filled($config['performer'] ?? null) ? (string) $config['performer'] : null,
            ...$this->sendOptions($config),
        ]);

        return $this->shapeMessage((array) $message);
    }
}
