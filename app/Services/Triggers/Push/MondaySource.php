<?php

namespace App\Services\Triggers\Push;

use App\Exceptions\WatchRefusedException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * monday.com triggers through webhooks on the board.
 *
 * monday.com has one webhook per event, so a watch holds one for a new item, or
 * two for a changed one (a column changed, or the name), shared by the board's
 * triggers. monday.com proves a new URL by posting a challenge and waiting for it
 * back, so the watch is saved first and the token URL (kept hashed) answers that.
 * A delivery only makes the trigger's own poll look now, so the token is the check.
 */
class MondaySource extends AbstractTokenWatchedSource
{
    private const string API = 'https://api.monday.com/v2';

    /**
     * The webhook events of each kind.
     *
     * @var array<string, list<string>>
     */
    private const array EVENTS = [
        'created' => ['create_item'],
        'changed' => ['change_column_value', 'change_name'],
    ];

    public function provider(): string
    {
        return 'monday';
    }

    protected function nodeTypes(): array
    {
        return ['monday_list_new_items', 'monday_list_updated_items'];
    }

    /**
     * A trigger that names its board.
     */
    public function supports(Trigger $trigger): bool
    {
        return parent::supports($trigger) && $this->watchKeyFor($trigger) !== null;
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $board = trim((string) ($this->nodeConfig($trigger)['board_id'] ?? ''));

        if (preg_match('/^\d+$/', $board) !== 1) {
            return null;
        }

        return ($trigger->config['node_type'] === 'monday_list_new_items' ? 'created' : 'changed').":{$board}";
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Someone may have removed a webhook: if one of ours is missing, make them all again.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        [, $board] = explode(':', $watch->resource_key);
        $found = (array) ($this->graphql($this->credentialOf($watch), 'query ($board: ID!) { webhooks(board_id: $board) { id } }', ['board' => $board])['webhooks'] ?? []);
        $present = array_map(fn (mixed $hook): string => is_array($hook) ? (string) ($hook['id'] ?? '') : '', $found);

        foreach ($this->idsOf($watch) as $id) {
            if (! in_array($id, $present, true)) {
                $this->register($watch);

                return;
            }
        }

        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $credential = $this->credentialOf($watch);

        foreach ($this->idsOf($watch) as $id) {
            $this->graphql($credential, 'mutation ($id: ID!) { delete_webhook(id: $id) { id } }', ['id' => $id], ignoreMissing: true);
        }
    }

    /**
     * Make the board's webhooks, with a fresh token URL.
     */
    private function register(ProviderWatch $watch): void
    {
        [$kind, $board] = explode(':', $watch->resource_key);
        $credential = $this->credentialOf($watch);
        $old = $this->idsOf($watch);

        $this->registerUrl($watch, function (string $url) use ($watch, $credential, $kind, $board): void {
            $ids = [];

            try {
                foreach (self::EVENTS[$kind] as $event) {
                    $created = $this->graphql($credential, 'mutation ($board: ID!, $url: String!, $event: WebhookEventType!) { create_webhook(board_id: $board, url: $url, event: $event) { id } }', ['board' => $board, 'url' => $url, 'event' => $event]);
                    $ids[] = (string) data_get($created, 'create_webhook.id');
                }
            } catch (Throwable $exception) {
                foreach ($ids as $id) {
                    $this->graphql($credential, 'mutation ($id: ID!) { delete_webhook(id: $id) { id } }', ['id' => $id], ignoreMissing: true);
                }

                throw $exception;
            }

            $watch->external_id = implode(',', $ids);
        });

        // The old webhooks, if there were any, point at a token that no longer exists.
        foreach (array_diff($old, $this->idsOf($watch)) as $id) {
            $this->graphql($credential, 'mutation ($id: ID!) { delete_webhook(id: $id) { id } }', ['id' => $id], ignoreMissing: true);
        }

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * @return list<string>
     */
    private function idsOf(ProviderWatch $watch): array
    {
        return array_values(array_filter(explode(',', (string) $watch->external_id), fn (string $id): bool => $id !== ''));
    }

    /**
     * Run a GraphQL call; monday.com answers a refusal with HTTP 200 and `errors`.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     *
     * @throws WatchRefusedException
     */
    private function graphql(ConnectorCredential $credential, string $query, array $variables, bool $ignoreMissing = false): array
    {
        $response = $this->mondayHttp($credential)->post(self::API, ['query' => $query, 'variables' => (object) $variables]);
        $message = $response->json('error_message') ?? $response->json('errors.0.message');

        if ($message !== null && $ignoreMissing) {
            return [];
        }

        if ($message !== null) {
            throw new WatchRefusedException('monday.com refused the webhook: '.$message);
        }

        return (array) ($this->ok($response)->json('data') ?? []);
    }

    private function mondayHttp(ConnectorCredential $credential): PendingRequest
    {
        return Http::withHeaders(['Authorization' => $this->tokens->accessToken($credential), 'API-Version' => '2024-10'])->acceptJson()->timeout(30);
    }
}
