<?php

namespace App\Nodes\Integrations\Telegram;

class TelegramNewJoinRequestNode extends AbstractTelegramTriggerNode
{
    public function type(): string
    {
        return 'telegram_new_join_request';
    }

    public function name(): string
    {
        return 'Telegram: Join Request';
    }

    public function description(): string
    {
        return 'Starts a flow when someone asks to join a group or channel that approves its members. Pair it with Approve or Decline Join Request. Needs instant delivery.';
    }

    public function kind(): string
    {
        return 'chat_join_request';
    }

    public function triggerName(): string
    {
        return 'Telegram: Join request';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone asks to join a chat the bot administers.';
    }
}
