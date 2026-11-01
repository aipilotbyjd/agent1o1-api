<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramSendPhotoNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_send_photo';
    }

    public function name(): string
    {
        return 'Telegram: Send Photo';
    }

    public function description(): string
    {
        return 'Sends a photo from a link or a Telegram file id, with a caption.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'photo'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'photo' => Field::text('Photo', 'A link to the image (up to 5 MB) or the file id of one Telegram already has.', 'https://example.com/file'),
                'caption' => Field::textarea('Caption', 'Up to 1024 characters.'),
                ...$this->sendFields(buttons: true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $message = $this->call($run, $config, 'sendPhoto', [
            'chat_id' => $chat,
            'photo' => $this->requiredString($config, 'photo', 'The photo'),
            'caption' => $this->caption($config),
            ...$this->sendOptions($config),
        ]);

        return $this->shapeMessage((array) $message);
    }
}
