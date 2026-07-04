<?php

namespace App\Engine\Polling\Executors;

use App\Contracts\TriggerExecutor;
use App\Engine\Trigger\TriggerEventDispatcher;
use App\Models\Trigger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Polls Gmail for new unread messages matching an optional search query and
 * dispatches one trigger event per new message.
 */
class GmailExecutor implements TriggerExecutor
{
    public function __construct(private readonly TriggerEventDispatcher $dispatcher) {}

    public function execute(Trigger $trigger): void
    {
        $credential = $trigger->credential;

        if (! $credential) {
            Log::warning("Gmail trigger {$trigger->id} has no credential");

            return;
        }

        $accessToken = $credential->getDecryptedData()['access_token'] ?? null;

        if (! $accessToken) {
            Log::warning("Gmail trigger {$trigger->id} credential missing access_token");

            return;
        }

        $searchQuery = $trigger->getFieldValue('search_query') ?? '';
        $query = trim('is:unread '.$searchQuery);

        try {
            $listResponse = Http::withToken($accessToken)
                ->get('https://gmail.googleapis.com/gmail/v1/users/me/messages', [
                    'q' => $query,
                    'maxResults' => 50,
                ]);

            if (! $listResponse->successful()) {
                Log::warning("Gmail trigger {$trigger->id} list got HTTP {$listResponse->status()}");

                return;
            }

            $messages = $listResponse->json('messages') ?? [];
            $seenIds = $trigger->polling_last_seen_ids ?? [];
            $newSeenIds = $seenIds;

            foreach ($messages as $msg) {
                $id = $msg['id'] ?? null;

                if ($id === null || in_array($id, $seenIds, true)) {
                    continue;
                }

                $msgResponse = Http::withToken($accessToken)
                    ->get("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$id}", [
                        'format' => 'metadata',
                        'metadataHeaders' => ['From', 'To', 'Subject', 'Date'],
                    ]);

                if (! $msgResponse->successful()) {
                    continue;
                }

                $full = $msgResponse->json();
                $headers = collect($full['payload']['headers'] ?? [])
                    ->keyBy('name')
                    ->map(fn ($h) => $h['value'])
                    ->all();

                $item = [
                    'id' => $id,
                    'thread_id' => $full['threadId'] ?? null,
                    'from' => $headers['From'] ?? null,
                    'to' => $headers['To'] ?? null,
                    'subject' => $headers['Subject'] ?? null,
                    'date' => $headers['Date'] ?? null,
                    'snippet' => $full['snippet'] ?? null,
                    'labels' => $full['labelIds'] ?? [],
                ];

                $newSeenIds[] = $id;
                $this->dispatcher->dispatch($trigger, ['item' => $item, 'item_id' => $id], 'gmail', 'poll', $id);
            }

            $trigger->update([
                'polling_last_seen_ids' => array_slice($newSeenIds, -500),
            ]);
        } catch (\Throwable $e) {
            Log::error("Gmail trigger {$trigger->id} failed", ['error' => $e->getMessage()]);
        }
    }
}
