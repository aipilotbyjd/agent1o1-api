<?php

namespace App\Nodes\Integrations\Telegram;

class TelegramEditedMessageNode extends AbstractTelegramTriggerNode
{
    public function type(): string
    {
        return 'telegram_edited_message';
    }

    public function name(): string
    {
        return 'Telegram: Message Edited';
    }

    public function description(): string
    {
        return 'Starts a flow when someone edits a message the bot can see. Needs instant delivery.';
    }

    public function kind(): string
    {
        return 'edited_message';
    }

    public function triggerName(): string
    {
        return 'Telegram: Message edited';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a message in a chat the bot is in is edited.';
    }
}
