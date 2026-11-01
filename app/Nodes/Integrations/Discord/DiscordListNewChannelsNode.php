<?php

namespace App\Nodes\Integrations\Discord;

use App\Contracts\SupportsTriggerMode;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;

/**
 * "New channel". Reads the server's channels every five minutes and starts a
 * flow for each one it hasn't seen.
 */
class DiscordListNewChannelsNode extends DiscordListChannelsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'discord_list_new_channels';
    }

    public function name(): string
    {
        return 'Discord: List New Channels';
    }

    public function description(): string
    {
        return 'Lists the channels of a server, oldest first. As a trigger it starts the flow for each new channel or category.';
    }

    /**
     * Oldest first, so a burst of channels runs in the order they were made.
     */
    public function execute(Run $run, array $config, array $context): array
    {
        $output = parent::execute($run, $config, $context);
        usort($output['channels'], fn (array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);

        return $output;
    }

    protected function triggerItemsPath(): ?string
    {
        return 'channels';
    }

    public function triggerName(): string
    {
        return 'Discord: New channel';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a channel or category is created in a Discord server. Checks every five minutes.';
    }
}
