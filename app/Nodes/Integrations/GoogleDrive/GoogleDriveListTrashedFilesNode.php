<?php

namespace App\Nodes\Integrations\GoogleDrive;

use App\Contracts\KeepsTriggerState;
use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

/**
 * Lists the files in the trash. As a trigger it fires when a file is trashed
 * or permanently deleted.
 *
 * Drive doesn't say when a file was trashed (outside shared drives), so the
 * trigger reads Drive's change feed instead: the first poll takes a start
 * token, and each poll after reads the changes since the last one, keeping the
 * files that are now trashed or gone. A changed file is reported once per
 * change, so trashing, restoring and trashing again fires twice.
 */
class GoogleDriveListTrashedFilesNode extends AbstractGoogleDriveNode implements KeepsTriggerState, SupportsTriggerMode
{
    /**
     * The fields of a change: when it happened, and enough of the file to act on.
     */
    private const string CHANGE_FIELDS = 'nextPageToken,newStartPageToken,changes(fileId,removed,time,file(id,name,mimeType,parents,webViewLink,modifiedTime,trashed,owners(displayName,emailAddress)))';

    /**
     * How many pages of changes one poll reads. A busy Drive carries on from
     * where it stopped at the next poll.
     */
    private const int MAX_PAGES = 5;

    public function type(): string
    {
        return 'google_drive_list_trashed_files';
    }

    public function name(): string
    {
        return 'Google Drive: List Trashed Files';
    }

    public function description(): string
    {
        return 'Lists the files in the trash. As a trigger it fires when a file is trashed or permanently deleted.';
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
                'folder_id' => Field::dynamic('In folder', 'google_drive.folders', 'Only files that were directly inside this folder. Leave empty for all of Drive.', '1AbCdEfGhIjKlMnOp'),
                'file_type' => Field::select('File type', self::FILE_TYPES, 'Leave empty for every type.'),
                'page_size' => Field::integer('Max results', 'The files to list. As a trigger every trashed file is reported.', 25, 1, 1000),
            ],
        ];
    }

    /**
     * The files in the trash now, most recently changed first.
     */
    public function execute(Run $run, array $config, array $context): array
    {
        $trashed = $this->fileQuery([...$config, 'query' => '', 'include_trashed' => true]);

        return $this->get($run, '/files', $config, [
            'q' => $trashed === '' ? 'trashed = true' : "{$trashed} and trashed = true",
            'pageSize' => $config['page_size'] ?? 25,
            'orderBy' => 'modifiedTime desc',
            'fields' => self::LIST_FIELDS,
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
        ]);
    }

    /**
     * Not used: trashed files come from pollSince().
     */
    public function triggerItems(array $output, array $config): array
    {
        return [];
    }

    /**
     * The files trashed or deleted since the last poll.
     */
    public function pollSince(Run $run, array $config, ?array $state): array
    {
        $token = $state['page_token'] ?? null;

        if ($token === null) {
            return ['items' => [], 'state' => ['page_token' => $this->startToken($run, $config)]];
        }

        $items = [];
        $pageSize = max(1, min(100, (int) config('triggers.poll_max_items')));

        for ($page = 0; $page < self::MAX_PAGES && count($items) < $pageSize; $page++) {
            try {
                $changes = $this->get($run, '/changes', $config, [
                    'pageToken' => $token,
                    // Never more than the poll can take, so none is cut off and lost.
                    'pageSize' => $pageSize - count($items),
                    'includeRemoved' => 'true',
                    'supportsAllDrives' => 'true',
                    'includeItemsFromAllDrives' => 'true',
                    'fields' => self::CHANGE_FIELDS,
                ]);
            } catch (RuntimeException $e) {
                // A token Drive no longer knows (it expires after a long gap):
                // start again from now rather than failing every poll after it.
                if ($page === 0 && stripos($e->getMessage(), 'page token') !== false) {
                    return ['items' => [], 'state' => ['page_token' => $this->startToken($run, $config)]];
                }

                throw $e;
            }

            foreach ((array) ($changes['changes'] ?? []) as $change) {
                $item = is_array($change) ? $this->trashedItem($change, $config) : null;

                if ($item !== null) {
                    $items[] = $item;
                }
            }

            // The last page names where the next poll starts; an earlier one,
            // the page after it.
            $token = $changes['nextPageToken'] ?? $changes['newStartPageToken'] ?? $token;

            if (! isset($changes['nextPageToken'])) {
                break;
            }
        }

        return ['items' => $items, 'state' => ['page_token' => $token]];
    }

    /**
     * A trashed (or gone) file shaped like a file from the list nodes, plus
     * when it happened, or null when this change is not one to report.
     *
     * @param  array<string, mixed>  $change
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    private function trashedItem(array $change, array $config): ?array
    {
        $file = (array) ($change['file'] ?? []);
        $removed = ($change['removed'] ?? false) === true;

        if (! $removed && ($file['trashed'] ?? false) !== true) {
            return null;
        }

        $folder = trim((string) ($config['folder_id'] ?? ''));

        // A file deleted for good has no details left, so a filter can't be
        // checked against it and it is not reported.
        if ($folder !== '' && ($file === [] || ! in_array($folder, (array) ($file['parents'] ?? []), true))) {
            return null;
        }

        $type = trim((string) ($config['file_type'] ?? ''));
        $mime = (string) ($file['mimeType'] ?? '');

        if ($type !== '' && ($file === [] || ! (str_ends_with($type, '/*') ? str_starts_with($mime, substr($type, 0, -1)) : $mime === $type))) {
            return null;
        }

        return [
            ...$file,
            'id' => (string) ($change['fileId'] ?? $file['id'] ?? ''),
            'trashed_at' => $change['time'] ?? null,
            'permanently_deleted' => $removed,
        ];
    }

    private function startToken(Run $run, array $config): string
    {
        $token = $this->get($run, '/changes/startPageToken', $config, ['supportsAllDrives' => 'true'])['startPageToken'] ?? null;

        return is_string($token) && $token !== '' ? $token : throw new RuntimeException('Google Drive did not return a start token for changes.');
    }

    /**
     * A file is reported once per change: its id and when it changed.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['id']) && $item['id'] !== '' ? $item['id'].':trashed:'.($item['trashed_at'] ?? '') : '';
    }

    /**
     * The file as it is.
     */
    public function expandTriggerItem(Run $run, array $config, array $item): array
    {
        return $item;
    }

    /**
     * A trashed file, for the sample in the editor.
     */
    public function triggerSampleItems(Run $run, array $config): array
    {
        return [[
            'id' => 'example-file-id',
            'name' => 'Quarterly report.pdf',
            'mimeType' => 'application/pdf',
            'trashed' => true,
            'trashed_at' => now()->toIso8601String(),
            'permanently_deleted' => false,
        ]];
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Drive: File trashed';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a file is moved to the trash or permanently deleted.';
    }

    /**
     * Every five minutes, and at once when Drive says something changed.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }

    /**
     * The node's own config.
     */
    public function triggerReadConfig(array $config): array
    {
        return $config;
    }

    /**
     * In the order Drive reports the changes.
     */
    public function triggerListsNewestFirst(): bool
    {
        return false;
    }
}
