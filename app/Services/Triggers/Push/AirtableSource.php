<?php

namespace App\Services\Triggers\Push;

use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Airtable triggers through a webhook on the base.
 *
 * One webhook per base, shared by the base's triggers. Airtable sends a bare ping
 * when something changed (the changes themselves are fetched separately), which
 * is all a delivery needs to be here: it makes the trigger's own poll look now.
 * Airtable switches a webhook off after seven days unless it is refreshed, so the
 * daily check refreshes it, and makes it again if it is gone. The URL carries a
 * random token (kept hashed) as the check.
 */
class AirtableSource extends AbstractTokenWatchedSource
{
    private const string API = 'https://api.airtable.com/v0';

    public function provider(): string
    {
        return 'airtable';
    }

    protected function nodeTypes(): array
    {
        return ['airtable_list_new_records', 'airtable_list_updated_records'];
    }

    /**
     * A trigger that names its base.
     */
    public function supports(Trigger $trigger): bool
    {
        return parent::supports($trigger) && $this->watchKeyFor($trigger) !== null;
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $base = trim((string) ($this->nodeConfig($trigger)['base_id'] ?? ''));

        return preg_match('/^app[A-Za-z0-9]+$/', $base) === 1 ? $base : null;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Refresh the webhook (it lasts seven days); if Airtable no longer has it, make it again.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $response = $this->airtableHttp($this->credentialOf($watch))->post(self::API."/bases/{$watch->resource_key}/webhooks/".rawurlencode((string) $watch->external_id).'/refresh');

        if (in_array($response->status(), [404, 422], true)) {
            $this->register($watch);

            return;
        }

        $this->ok($response);

        $watch->renew_at = now()->addDays(3);
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $response = $this->airtableHttp($this->credentialOf($watch))->delete(self::API."/bases/{$watch->resource_key}/webhooks/".rawurlencode((string) $watch->external_id));

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Make the base's webhook, with a fresh token URL.
     */
    private function register(ProviderWatch $watch): void
    {
        $http = $this->airtableHttp($this->credentialOf($watch));
        $old = $watch->external_id;

        $this->registerUrl($watch, function (string $url) use ($watch, $http): void {
            $response = $this->ok($http->post(self::API."/bases/{$watch->resource_key}/webhooks", [
                'notificationUrl' => $url,
                'specification' => ['options' => ['filters' => ['dataTypes' => ['tableData']]]],
            ]));
            $watch->external_id = (string) $response->json('id');
        });

        // The old webhook, if there was one, points at a token that no longer exists.
        if ($old !== null && $old !== '' && $old !== $watch->external_id) {
            $http->delete(self::API."/bases/{$watch->resource_key}/webhooks/".rawurlencode((string) $old));
        }

        $watch->expires_at = null;
        $watch->renew_at = now()->addDays(3);
    }

    private function airtableHttp(ConnectorCredential $credential): PendingRequest
    {
        return Http::withToken($this->tokens->accessToken($credential))->acceptJson()->timeout(30);
    }
}
