<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramSendMessageNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_send_message';
    }

    public function name(): string
    {
        return 'Telegram: Send Message';
    }

    public function description(): string
    {
        return 'Sends a text message to a chat, group or channel as the bot, with optional formatting, buttons and a reply.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'text' => Field::textarea('Message', 'Up to 4096 characters.'),
                'disable_link_preview' => Field::advanced(Field::boolean('Disable link preview', 'Do not show a preview of the first link.')),
                ...$this->sendFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);

        $message = $this->call($run, $config, 'sendMessage', [
            'chat_id' => $chat,
            'text' => $this->text($config),
            'link_preview_options' => filter_var($config['disable_link_preview'] ?? false, FILTER_VALIDATE_BOOLEAN) ? ['is_disabled' => true] : null,
            ...$this->sendOptions($config),
        ]);

        return $this->shapeMessage((array) $message);
    }
}
