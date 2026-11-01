<?php

namespace App\Nodes\Integrations\Telegram;

class TelegramNewChannelPostNode extends AbstractTelegramTriggerNode
{
    public function type(): string
    {
        return 'telegram_new_channel_post';
    }

    public function name(): string
    {
        return 'Telegram: New Channel Post';
    }

    public function description(): string
    {
        return 'Starts a flow when a post is published in a channel the bot is an admin of. Needs instant delivery.';
    }

    public function kind(): string
    {
        return 'channel_post';
    }

    public function triggerName(): string
    {
        return 'Telegram: New channel post';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a post is published in a channel the bot manages.';
    }
}
