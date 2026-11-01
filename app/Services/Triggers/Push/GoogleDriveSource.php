<?php

namespace App\Services\Triggers\Push;

use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;

/**
 * "New file" in Google Drive through the account's change feed. One channel
 * per account, shared by every Drive trigger on it; each trigger's own poll
 * applies its search.
 */
class GoogleDriveSource extends GoogleChannelSource
{
    public function provider(): string
    {
        return 'google_drive';
    }

    protected function nodeTypes(): array
    {
        return ['google_drive_list_files', 'google_drive_list_updated_files', 'google_drive_list_trashed_files'];
    }

    protected function api(): string
    {
        return 'https://www.googleapis.com/drive/v3';
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        return 'changes';
    }

    protected function openChannel(PendingRequest $http, ProviderWatch $watch, array $channel): array
    {
        $pageToken = $this->ok($http->get($this->api().'/changes/startPageToken'))->json('startPageToken');

        return $this->ok($http->post($this->api().'/changes/watch?pageToken='.urlencode((string) $pageToken), $channel))->json();
    }
}
