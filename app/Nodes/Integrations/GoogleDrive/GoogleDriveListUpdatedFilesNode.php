<?php

namespace App\Nodes\Integrations\GoogleDrive;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GoogleDriveListUpdatedFilesNode extends AbstractGoogleDriveNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * A file edited this soon after it was made is the creation itself (an
     * editor's first save), not a change to it.
     */
    private const int CREATION_GRACE_SECONDS = 30;

    /**
     * How far back a poll looks for changes since it last ran.
     */
    private const int TRIGGER_WINDOW_MINUTES = 360;

    public function type(): string
    {
        return 'google_drive_list_updated_files';
    }

    public function name(): string
    {
        return 'Google Drive: List Updated Files';
    }

    public function description(): string
    {
        return 'Lists files that were edited lately, most recently changed first.';
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
                'changed_within_minutes' => Field::integer('Changed within (minutes)', 'Only files edited this recently.', 60, 1, 43200),
                'page_size' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listFiles(
            $run,
            $config,
            'modifiedTime desc',
            now()->subMinutes((int) ($config['changed_within_minutes'] ?? 60))->toRfc3339String(),
        );
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'files';
    }

    /**
     * Files edited after they were made, not the act of making them.
     */
    public function triggerItems(array $output, array $config): array
    {
        return array_values(array_filter(
            (array) ($output['files'] ?? []),
            fn (mixed $file): bool => is_array($file) && $this->secondsEdited($file) > self::CREATION_GRACE_SECONDS,
        ));
    }

    /**
     * A file is a new item each time it is edited: its id and last change.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['id']) ? $item['id'].':'.($item['modifiedTime'] ?? '') : '';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Drive: File updated';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a file is edited.';
    }

    /**
     * Read the changes since the poll last ran, and a wide page for a busy Drive.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'changed_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'page_size' => 100];
    }

    /**
     * Newest first, as the list is ordered by when files were changed.
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
