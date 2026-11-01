<?php

namespace App\Services\Triggers\Push;

use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * ClickUp triggers through a webhook on the list.
 *
 * One webhook per list and kind (created or changed), shared by the list's
 * triggers. ClickUp signs deliveries with a secret it makes, but a delivery here
 * only makes the trigger's own poll look now, so the URL's random token (kept
 * hashed) is the check. ClickUp marks a webhook that keeps failing as failing or
 * suspended, which the daily check notices.
 */
class ClickUpSource extends AbstractTokenWatchedSource
{
    private const string API = 'https://api.clickup.com/api/v2';

    /**
     * The webhook events of each kind.
     *
     * @var array<string, list<string>>
     */
    private const array EVENTS = [
        'created' => ['taskCreated'],
        'changed' => ['taskUpdated', 'taskStatusUpdated', 'taskPriorityUpdated', 'taskAssigneeUpdated', 'taskDueDateUpdated', 'taskTagUpdated', 'taskMoved', 'taskCommentPosted', 'taskCommentUpdated', 'taskTimeEstimateUpdated', 'taskTimeTrackedUpdated'],
    ];

    public function provider(): string
    {
        return 'clickup';
    }

    protected function nodeTypes(): array
    {
        return ['clickup_list_new_tasks', 'clickup_list_updated_tasks'];
    }

    /**
     * A trigger that names its workspace and list.
     */
    public function supports(Trigger $trigger): bool
    {
        return parent::supports($trigger) && $this->watchKeyFor($trigger) !== null;
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $config = $this->nodeConfig($trigger);
        $workspace = trim((string) ($config['workspace_id'] ?? ''));
        $list = trim((string) ($config['list_id'] ?? ''));

        if (preg_match('/^\d+$/', $workspace) !== 1 || preg_match('/^\d+$/', $list) !== 1) {
            return null;
        }

        return ($trigger->config['node_type'] === 'clickup_list_new_tasks' ? 'created' : 'changed').":{$workspace}:{$list}";
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * ClickUp stops a webhook that keeps failing, or someone removed it: make it again if so.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        [, $workspace] = explode(':', $watch->resource_key);
        $webhooks = (array) $this->ok($this->clickupHttp($this->credentialOf($watch))->get(self::API."/team/{$workspace}/webhook"))->json('webhooks', []);
        $found = collect($webhooks)->first(fn (mixed $hook): bool => is_array($hook) && (string) ($hook['id'] ?? '') === (string) $watch->external_id);

        if ($found === null || ($found['health']['status'] ?? 'active') !== 'active') {
            $this->register($watch);

            return;
        }

        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $response = $this->clickupHttp($this->credentialOf($watch))->delete(self::API.'/webhook/'.rawurlencode((string) $watch->external_id));

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Make the list's webhook, with a fresh token URL.
     */
    private function register(ProviderWatch $watch): void
    {
        [$kind, $workspace, $list] = explode(':', $watch->resource_key);
        $http = $this->clickupHttp($this->credentialOf($watch));
        $old = $watch->external_id;

        $this->registerUrl($watch, function (string $url) use ($watch, $http, $kind, $workspace, $list): void {
            $response = $this->ok($http->post(self::API."/team/{$workspace}/webhook", ['endpoint' => $url, 'events' => self::EVENTS[$kind], 'list_id' => (int) $list]));
            $watch->external_id = (string) $response->json('id');
        });

        // The old webhook, if there was one, points at a token that no longer exists.
        if ($old !== null && $old !== '' && $old !== $watch->external_id) {
            $http->delete(self::API.'/webhook/'.rawurlencode((string) $old));
        }

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    private function clickupHttp(ConnectorCredential $credential): PendingRequest
    {
        return Http::withHeaders(['Authorization' => $this->tokens->accessToken($credential)])->acceptJson()->timeout(30);
    }
}
