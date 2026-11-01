<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramSendStickerNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_send_sticker';
    }

    public function name(): string
    {
        return 'Telegram: Send Sticker';
    }

    public function description(): string
    {
        return 'Sends a sticker from a link to a WEBP file or a Telegram file id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'sticker'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'sticker' => Field::text('Sticker', 'A link to a WEBP, TGS or WEBM sticker or the file id of one Telegram already has.', 'https://example.com/file'),
                ...$this->sendFields(buttons: true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $message = $this->call($run, $config, 'sendSticker', [
            'chat_id' => $chat,
            'sticker' => $this->requiredString($config, 'sticker', 'The sticker'),
            ...$this->sendOptions($config),
        ]);

        return $this->shapeMessage((array) $message);
    }
}
