<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramSendVideoNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_send_video';
    }

    public function name(): string
    {
        return 'Telegram: Send Video';
    }

    public function description(): string
    {
        return 'Sends a video from a link or a Telegram file id, with a caption.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'video'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'video' => Field::text('Video', 'A link to an MP4 video or the file id of one Telegram already has.', 'https://example.com/file'),
                'caption' => Field::textarea('Caption', 'Up to 1024 characters.'),
                'supports_streaming' => Field::advanced(Field::boolean('Play while downloading')),
                ...$this->sendFields(buttons: true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $message = $this->call($run, $config, 'sendVideo', [
            'chat_id' => $chat,
            'video' => $this->requiredString($config, 'video', 'The video'),
            'caption' => $this->caption($config),
            'supports_streaming' => filter_var($config['supports_streaming'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            ...$this->sendOptions($config),
        ]);

        return $this->shapeMessage((array) $message);
    }
}
