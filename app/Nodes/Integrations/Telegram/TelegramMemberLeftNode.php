<?php

namespace App\Nodes\Integrations\Telegram;

class TelegramMemberLeftNode extends AbstractTelegramTriggerNode
{
    public function type(): string
    {
        return 'telegram_member_left';
    }

    public function name(): string
    {
        return 'Telegram: Member Left';
    }

    public function description(): string
    {
        return 'Starts a flow when someone leaves, or is removed from, a group the bot is in. Needs instant delivery.';
    }

    public function kind(): string
    {
        return 'member_left';
    }

    public function triggerName(): string
    {
        return 'Telegram: Member left';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone leaves or is removed from a group the bot is in.';
    }
}
