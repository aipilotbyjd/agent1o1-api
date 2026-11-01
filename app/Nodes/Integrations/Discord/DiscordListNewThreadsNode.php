<?php

namespace App\Nodes\Integrations\Discord;

use App\Contracts\SupportsTriggerMode;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;

/**
 * "New thread". Discord pushes thread creation only over the gateway, so this
 * reads the server's active threads every two minutes. A thread made and
 * archived again between two reads is not seen.
 */
class DiscordListNewThreadsNode extends DiscordListActiveThreadsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'discord_list_new_threads';
    }

    public function name(): string
    {
        return 'Discord: List New Threads';
    }

    public function description(): string
    {
        return 'Lists the active threads of a server, newest first. As a trigger it starts the flow for each new thread (and forum post).';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'threads';
    }

    public function triggerName(): string
    {
        return 'Discord: New thread';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a thread or forum post is created in a Discord server. Checks every two minutes.';
    }

    public function triggerPollMinutes(): int
    {
        return 2;
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
