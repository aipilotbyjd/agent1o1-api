<?php

namespace App\Engine\Polling\Executors;

use App\Contracts\TriggerExecutor;
use App\Engine\Trigger\TriggerEventDispatcher;
use App\Models\Trigger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenericApiPollingExecutor implements TriggerExecutor
{
    public function __construct(private readonly TriggerEventDispatcher $dispatcher) {}

    /**
     * Poll a configured API endpoint, diff against previously seen IDs,
     * and dispatch one trigger event per new item.
     */
    public function execute(Trigger $trigger): void
    {
        $settings = $trigger->settings ?? [];
        $url = $settings['polling_url'] ?? null;

        if (! $url) {
            return;
        }

        try {
            $request = Http::timeout(30);

            if ($credential = $trigger->credential) {
                $data = $credential->getDecryptedData();
                $request = match ($data['type'] ?? 'bearer') {
                    'bearer', 'oauth2' => $request->withToken($data['access_token'] ?? ''),
                    'api_key' => $request->withHeaders([$data['header_name'] ?? 'X-Api-Key' => $data['api_key'] ?? '']),
                    'basic' => $request->withBasicAuth($data['username'] ?? '', $data['password'] ?? ''),
                    default => $request,
                };
            }

            $response = $request->get($url, $settings['polling_params'] ?? []);

            if (! $response->successful()) {
                Log::warning("Polling trigger {$trigger->id} got HTTP {$response->status()}");

                return;
            }

            $items = data_get($response->json(), $settings['items_path'] ?? '', $response->json());

            if (! is_array($items)) {
                return;
            }

            $idField = $settings['id_field'] ?? 'id';
            $seenIds = $trigger->polling_last_seen_ids ?? [];
            $newSeenIds = $seenIds;

            foreach ($items as $item) {
                $itemId = (string) data_get($item, $idField, md5(json_encode($item)));

                if (in_array($itemId, $seenIds, true)) {
                    continue;
                }

                $newSeenIds[] = $itemId;
                $this->dispatcher->dispatch($trigger, ['item' => $item, 'item_id' => $itemId]);
            }

            // Keep the seen-ID window bounded
            $trigger->update([
                'polling_last_seen_ids' => array_slice($newSeenIds, -500),
            ]);
        } catch (\Throwable $e) {
            Log::error("Polling trigger {$trigger->id} failed", ['error' => $e->getMessage()]);
        }
    }
}
