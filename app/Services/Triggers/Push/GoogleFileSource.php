<?php

namespace App\Services\Triggers\Push;

use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;

/**
 * Changes to one Google file (a spreadsheet, a document) through Drive's push
 * channel on that file. Every trigger on the same file shares one channel;
 * each trigger's own poll then reads what changed.
 *
 * Needs the connected account to be allowed to see the file's metadata in
 * Drive, which the Sheets and Docs connectors ask for.
 */
abstract class GoogleFileSource extends GoogleChannelSource
{
    /**
     * The node config field that holds the file's ID.
     */
    abstract protected function fileIdField(): string;

    protected function api(): string
    {
        return 'https://www.googleapis.com/drive/v3';
    }

    /**
     * Only a trigger that names a file: one whose file is filled in at run
     * time has nothing to watch, and just keeps polling.
     */
    public function supports(Trigger $trigger): bool
    {
        return parent::supports($trigger) && $this->watchKeyFor($trigger) !== null;
    }

    /**
     * Nothing to watch until the trigger names a file, and not a file that is
     * a `{{template}}` filled in at run time.
     */
    public function watchKeyFor(Trigger $trigger): ?string
    {
        $id = $this->nodeConfig($trigger)[$this->fileIdField()] ?? null;

        return is_string($id) && $id !== '' && ! str_contains($id, '{{') ? 'file:'.$id : null;
    }

    protected function openChannel(PendingRequest $http, ProviderWatch $watch, array $channel): array
    {
        $fileId = substr($watch->resource_key, strlen('file:'));

        return $this->ok($http->post($this->api().'/files/'.rawurlencode($fileId).'/watch?supportsAllDrives=true', $channel))->json();
    }
}
