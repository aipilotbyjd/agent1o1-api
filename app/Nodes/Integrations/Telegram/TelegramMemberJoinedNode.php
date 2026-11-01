<?php

namespace App\Nodes\Integrations\Telegram;

class TelegramMemberJoinedNode extends AbstractTelegramTriggerNode
{
    public function type(): string
    {
        return 'telegram_member_joined';
    }

    public function name(): string
    {
        return 'Telegram: Member Joined';
    }

    public function description(): string
    {
        return 'Starts a flow when someone joins a group the bot is in. Needs instant delivery.';
    }

    public function kind(): string
    {
        return 'member_joined';
    }

    public function triggerName(): string
    {
        return 'Telegram: Member joined';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone joins a group or supergroup the bot is in.';
    }
}
