<?php

namespace App\Services\Triggers\Push;

use App\Exceptions\ConnectorException;
use App\Exceptions\WatchRefusedException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * New and updated Jira issues through a dynamic webhook, shared by every trigger
 * on the same site, event and project.
 *
 * An OAuth app registers webhooks for the signed-in person's site
 * (`POST /webhook`); they need the `manage:jira-webhook` scope (asked for while
 * instant delivery is on) and last 30 days, so the renewal extends each one
 * well before then, and makes it again if Jira no longer knows it. Jira doesn't
 * sign these deliveries, so each watch gets a random token in its callback URL
 * and the ingress finds the watch by that token. The trigger's own poll
 * fetches the issues, with its real filters.
 */
class JiraSource extends AbstractWatchedSource
{
    private const string GATEWAY = 'https://api.atlassian.com';

    /**
     * The webhook event each reader node is about.
     *
     * @var array<string, string>
     */
    private const array EVENTS = [
        'jira_list_new_issues' => 'jira:issue_created',
        'jira_list_updated_issues' => 'jira:issue_updated',
    ];

    /**
     * Jira expires a webhook after 30 days; it is extended after 20.
     */
    private const int LIFETIME_DAYS = 30;

    private const int RENEW_AFTER_DAYS = 20;

    public function provider(): string
    {
        return 'jira';
    }

    protected function nodeTypes(): array
    {
        return array_keys(self::EVENTS);
    }

    /**
     * "site|event|PROJECT": the site as the node names it ("-" for the first),
     * and the project only when the node chooses issues by fields and names one
     * (any other way of choosing is left to the poll, so the webhook is for the
     * whole site).
     */
    public function watchKeyFor(Trigger $trigger): ?string
    {
        $event = self::EVENTS[$trigger->config['node_type'] ?? ''] ?? null;
        $config = $this->nodeConfig($trigger);
        $site = strtolower(trim((string) ($config['site'] ?? '')));
        $project = strtoupper(trim((string) ($config['project'] ?? '')));
        $byFields = ($config['filter_mode'] ?? 'basic') === 'basic';

        return $event === null ? null : implode('|', [
            $site === '' || str_contains($site, '{{') ? '-' : $site,
            $event,
            $byFields && preg_match('/^[A-Z][A-Z0-9_]*$/', $project) === 1 ? $project : '*',
        ]);
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch, $this->credentialOf($watch));
    }

    /**
     * Extend the webhook; make it again when Jira has dropped it.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $credential = $this->credentialOf($watch);
        $cloud = $watch->meta['cloud_id'] ?? null;

        if ($watch->external_id === null || ! is_string($cloud)) {
            $this->register($watch, $credential);

            return;
        }

        $response = $this->http($credential)->withBody(json_encode(['webhookIds' => [(int) $watch->external_id]], JSON_THROW_ON_ERROR), 'application/json')
            ->put(self::GATEWAY."/ex/jira/{$cloud}/rest/api/3/webhook/refresh");

        if (in_array($response->status(), [400, 404, 410], true)) {
            $this->register($watch, $credential);

            return;
        }

        $this->ok($response);

        $expires = $response->json('expirationDate');
        $watch->expires_at = is_string($expires) ? Carbon::parse($expires) : now()->addDays(self::LIFETIME_DAYS);
        $watch->renew_at = now()->addDays(self::RENEW_AFTER_DAYS);
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $cloud = $watch->meta['cloud_id'] ?? null;

        if ($watch->external_id !== null && is_string($cloud)) {
            $this->http($this->credentialOf($watch))
                ->withBody(json_encode(['webhookIds' => [(int) $watch->external_id]], JSON_THROW_ON_ERROR), 'application/json')
                ->send('DELETE', self::GATEWAY."/ex/jira/{$cloud}/rest/api/3/webhook");
        }
    }

    /**
     * The hash of the watch's callback token: what a delivery is matched on.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        $hash = $watch?->meta['token_hash'] ?? null;

        return is_string($hash) ? $hash : null;
    }

    /**
     * Registering a webhook needs the `manage:jira-webhook` scope.
     */
    public function accessWarning(Trigger $trigger): ?string
    {
        $scope = ConnectorCredential::query()->where('workspace_id', $trigger->workspace_id)->find($trigger->credential_id)?->data['scope'] ?? null;

        return is_string($scope) && ! in_array('manage:jira-webhook', preg_split('/\s+/', $scope) ?: [], true)
            ? 'This Jira account was connected without permission to register webhooks, so the trigger checks on a timer. Reconnect it to get instant delivery.'
            : null;
    }

    /**
     * Register the webhook with Jira and record it on the watch. A new token
     * each time, so a webhook made again can't be reached by the old URL.
     *
     * @throws ConnectorException when Atlassian refuses the account
     * @throws WatchRefusedException when Jira rejects the registration
     * @throws RequestException when the call fails
     */
    private function register(ProviderWatch $watch, ConnectorCredential $credential): void
    {
        [$site, $event, $project] = explode('|', $watch->resource_key, 3);
        $cloud = $this->cloudId($credential, $site);
        $token = $this->newSecret();

        $answer = $this->ok($this->http($credential)->post(self::GATEWAY."/ex/jira/{$cloud}/rest/api/3/webhook", [
            'url' => $this->callbackUrl().'?token='.$token,
            'webhooks' => [array_filter([
                'events' => [$event],
                'jqlFilter' => $project === '*' ? null : 'project = "'.$project.'"',
            ])],
        ]))->json();

        $result = (array) data_get($answer, 'webhookRegistrationResult.0', []);

        if (! isset($result['createdWebhookId'])) {
            throw new WatchRefusedException('Jira did not register the webhook: '.implode('; ', array_map('strval', (array) ($result['errors'] ?? ['no reason given']))).'.');
        }

        $watch->external_id = (string) $result['createdWebhookId'];
        $watch->secret = $token;
        $watch->meta = [...(array) $watch->meta, 'cloud_id' => $cloud, 'event' => $event, 'project' => $project, 'token_hash' => hash('sha256', $token)];
        $watch->expires_at = now()->addDays(self::LIFETIME_DAYS);
        $watch->renew_at = now()->addDays(self::RENEW_AFTER_DAYS);
    }

    /**
     * The cloud id of the site the node names, or the account's first.
     *
     * @throws RuntimeException when the account can't open that site
     */
    private function cloudId(ConnectorCredential $credential, string $site): string
    {
        $sites = (array) $this->ok($this->http($credential)->get(self::GATEWAY.'/oauth/token/accessible-resources'))->json();

        foreach ($sites as $candidate) {
            $names = array_map('strtolower', array_filter([
                (string) ($candidate['id'] ?? ''),
                rtrim((string) ($candidate['url'] ?? ''), '/'),
                (string) ($candidate['name'] ?? ''),
                (string) preg_replace('~^https?://~', '', rtrim((string) ($candidate['url'] ?? ''), '/')),
            ]));

            if (is_array($candidate) && isset($candidate['id']) && ($site === '-' || in_array(rtrim($site, '/'), $names, true))) {
                return (string) $candidate['id'];
            }
        }

        throw new RuntimeException($site === '-' ? 'This Atlassian account has no Jira site.' : "The connected account can't open the Jira site \"{$site}\".");
    }
}
