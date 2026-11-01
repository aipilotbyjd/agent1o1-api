<?php

namespace App\Nodes\Integrations\GoogleDrive;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GoogleDriveListFilesNode extends AbstractGoogleDriveNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'google_drive_list_files';
    }

    public function name(): string
    {
        return 'Google Drive: List Files';
    }

    public function description(): string
    {
        return 'Lists files in Google Drive matching a query.';
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
                ...$this->fileFilterFields(),
                'page_size' => Field::integer('Max results', null, 10, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listFiles($run, $config, 'createdTime desc');
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
        return 'Google Drive: New file';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a new file matching the search appears.';
    }

    /**
     * Read a wider page than the node's default, so a burst between two polls
     * isn't cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'page_size' => max((int) ($config['page_size'] ?? 0), 100)];
    }

    /**
     * Newest first, as the list is ordered by when files were made: without an
     * order Drive lists in no useful sequence, and a page of 100 could miss
     * the newest file in a large Drive.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }
}
