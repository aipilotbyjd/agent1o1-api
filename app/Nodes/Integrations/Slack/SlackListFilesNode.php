<?php

namespace App\Nodes\Integrations\Slack;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class SlackListFilesNode extends AbstractSlackNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'slack_list_files';
    }

    public function name(): string
    {
        return 'Slack: List Files';
    }

    public function description(): string
    {
        return 'Lists files shared in Slack, newest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'Only files shared in this channel. Leave empty for the whole workspace.', 'C0123456789'),
                'types' => Field::select('File types', [
                    'all' => 'All files',
                    'images' => 'Images',
                    'pdfs' => 'PDFs',
                    'docs' => 'Documents',
                    'spaces' => 'Posts',
                    'snippets' => 'Snippets',
                    'zips' => 'Zip files',
                ], null, 'all'),
                'shared_within_hours' => Field::integer('Shared within (hours)', 'Only files shared this recently.', 24, 1, 8760),
                'count' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = trim((string) ($config['channel'] ?? ''));
        $types = (string) ($config['types'] ?? 'all');

        return $this->get($run, 'files.list', $config, array_filter([
            'channel' => $channel !== '' ? $channel : null,
            'types' => $types !== 'all' ? $types : null,
            'ts_from' => now()->subHours((int) ($config['shared_within_hours'] ?? 24))->getTimestamp(),
            'count' => $config['count'] ?? 50,
        ], fn ($value) => $value !== null));
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'files';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Slack: New file';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a file is shared in Slack.';
    }

    /**
     * Read a wider page than the node's default, so a burst between two polls
     * isn't cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'count' => max((int) ($config['count'] ?? 0), 100), 'shared_within_hours' => 24];
    }

    /**
     * Newest first, as Slack lists them.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * Slack tells us the moment a file is shared: a slow check.
     */
    public function triggerPollMinutes(): int
    {
        return 10;
    }
}
