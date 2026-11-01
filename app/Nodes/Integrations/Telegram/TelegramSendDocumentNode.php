<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramSendDocumentNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_send_document';
    }

    public function name(): string
    {
        return 'Telegram: Send Document';
    }

    public function description(): string
    {
        return 'Sends a file from a link or a Telegram file id (up to 50 MB through a link), with a caption.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'document'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'document' => Field::text('File', 'A link to the file or the file id of one Telegram already has.', 'https://example.com/file'),
                'caption' => Field::textarea('Caption', 'Up to 1024 characters.'),
                ...$this->sendFields(buttons: true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $message = $this->call($run, $config, 'sendDocument', [
            'chat_id' => $chat,
            'document' => $this->requiredString($config, 'document', 'The file'),
            'caption' => $this->caption($config),
            ...$this->sendOptions($config),
        ]);

        return $this->shapeMessage((array) $message);
    }
}
