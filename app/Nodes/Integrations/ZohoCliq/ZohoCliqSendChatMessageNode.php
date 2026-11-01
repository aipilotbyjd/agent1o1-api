<?php

namespace App\Nodes\Integrations\ZohoCliq;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoCliqSendChatMessageNode extends AbstractZohoCliqNode
{
    public function type(): string
    {
        return 'zoho_cliq_send_chat_message';
    }

    public function name(): string
    {
        return 'Zoho Cliq: Send Chat Message';
    }

    public function description(): string
    {
        return 'Posts a message to a chat (a group or direct chat) by its chat id.';
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
                'chat_id' => Field::text('Chat id', 'From a previous step, e.g. CT_2230...'),
                'text' => Field::textarea('Message'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chatId($config);

        $sent = $this->cliq($run, $config, 'POST', "/chats/{$chat}/message", [], ['text' => $this->requiredString($config, 'text', 'The message')]);

        return ['sent' => true, 'chat_id' => $chat, 'id' => isset($sent['message_id']) ? (string) $sent['message_id'] : (isset($sent['id']) ? (string) $sent['id'] : null)];
    }
}
