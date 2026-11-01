<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramSendChatActionNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_send_chat_action';
    }

    public function name(): string
    {
        return 'Telegram: Send Chat Action';
    }

    public function description(): string
    {
        return 'Shows "typing…" or "sending a photo…" in a chat for a few seconds, while a longer step runs.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'action' => Field::select('Action', ['typing' => 'Typing', 'upload_photo' => 'Sending a photo', 'record_video' => 'Recording a video', 'upload_video' => 'Sending a video', 'record_voice' => 'Recording a voice message', 'upload_voice' => 'Sending a voice message', 'upload_document' => 'Sending a file', 'find_location' => 'Choosing a location'], null, 'typing'),
                'thread_id' => Field::advanced(Field::integer('Topic (thread) id')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $action = (string) ($config['action'] ?? 'typing');
        $thread = $config['thread_id'] ?? null;

        $this->call($run, $config, 'sendChatAction', ['chat_id' => $chat, 'action' => $action, 'message_thread_id' => is_numeric($thread) ? (int) $thread : null]);

        return ['sent' => true, 'chat_id' => $chat, 'action' => $action];
    }
}
