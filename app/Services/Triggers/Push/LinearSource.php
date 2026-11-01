<?php

namespace App\Services\Triggers\Push;

use App\Exceptions\ConnectorException;
use App\Exceptions\WatchRefusedException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Linear issues and comments through a Linear webhook, shared by every trigger
 * on the same team and kind of record.
 *
 * Creating a webhook needs an account connected with Linear's `admin` scope
 * (the connector asks for it while instant delivery is on); without it Linear
 * refuses and the trigger keeps polling. A webhook is signed with its own
 * secret and names itself in every delivery. Linear switches a webhook off
 * after repeated failed deliveries, so the daily renewal looks at it and turns
 * it back on. The trigger's own poll fetches the items.
 */
class LinearSource extends AbstractWatchedSource
{
    private const string API = 'https://api.linear.app/graphql';

    /**
     * The record type each reader node is about.
     *
     * @var array<string, string>
     */
    private const array TYPES = [
        'linear_list_new_issues' => 'Issue',
        'linear_list_updated_issues' => 'Issue',
        'linear_list_new_comments' => 'Comment',
    ];

    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function provider(): string
    {
        return 'linear';
    }

    protected function nodeTypes(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * One webhook per team (or every public team) and record type: "eng:Issue".
     */
    public function watchKeyFor(Trigger $trigger): ?string
    {
        $type = self::TYPES[$trigger->config['node_type'] ?? ''] ?? null;
        $team = strtolower(trim((string) ($this->nodeConfig($trigger)['team'] ?? '')));

        return $type === null ? null : (($team === '' || str_contains($team, '{{') ? '*' : $team).':'.$type);
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        [$team, $type] = explode(':', $watch->resource_key, 2);

        $this->register($watch, $this->credentialOf($watch), $team, $type);
    }

    /**
     * Linear never expires a webhook, but switches one off after failed
     * deliveries: look at it, turn it back on, or make it again if it is gone.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $credential = $this->credentialOf($watch);
        $found = $watch->external_id === null ? null : $this->graphql($credential, 'query($id: String!) { webhook(id: $id) { id enabled } }', ['id' => $watch->external_id], tolerateErrors: true)['webhook'] ?? null;

        if (! is_array($found)) {
            [$team, $type] = explode(':', $watch->resource_key, 2);
            $this->register($watch, $credential, $team, $type);

            return;
        }

        if (($found['enabled'] ?? true) === false) {
            $this->graphql($credential, 'mutation($id: String!, $input: WebhookUpdateInput!) { webhookUpdate(id: $id, input: $input) { success } }', ['id' => $watch->external_id, 'input' => ['enabled' => true]]);
        }

        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        if ($watch->external_id !== null) {
            $this->graphql($this->credentialOf($watch), 'mutation($id: String!) { webhookDelete(id: $id) { success } }', ['id' => $watch->external_id], tolerateErrors: true);
        }
    }

    /**
     * The webhook's id: every delivery names the webhook it came from.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        return $watch?->external_id;
    }

    /**
     * Creating a webhook through the API needs the `admin` scope.
     */
    public function accessWarning(Trigger $trigger): ?string
    {
        $scope = ConnectorCredential::query()->where('workspace_id', $trigger->workspace_id)->find($trigger->credential_id)?->data['scope'] ?? null;

        return is_string($scope) && ! in_array('admin', preg_split('/[\s,]+/', $scope) ?: [], true)
            ? 'This Linear account was connected without permission to register webhooks, so the trigger checks on a timer. Reconnect it to get instant delivery.'
            : null;
    }

    /**
     * Make the webhook at Linear and record it on the watch.
     *
     * @throws ConnectorException when Linear says the account may not create webhooks
     */
    private function register(ProviderWatch $watch, ConnectorCredential $credential, string $team, string $type): void
    {
        $secret = $this->newSecret();
        $teamId = $team === '*' ? null : $this->teamId($credential, $team);

        $data = $this->graphql($credential, 'mutation($input: WebhookCreateInput!) { webhookCreate(input: $input) { success webhook { id enabled } } }', ['input' => array_filter([
            'url' => $this->callbackUrl(),
            'resourceTypes' => [$type],
            'teamId' => $teamId,
            'allPublicTeams' => $teamId === null ? true : null,
            'secret' => $secret,
            'label' => 'Flows',
            'enabled' => true,
        ], fn (mixed $value): bool => $value !== null)], permissionHint: true);

        $id = data_get($data, 'webhookCreate.webhook.id');

        if (data_get($data, 'webhookCreate.success') !== true || ! is_string($id)) {
            throw new RuntimeException('Linear did not create the webhook.');
        }

        $watch->external_id = $id;
        $watch->secret = $secret;
        $watch->meta = [...(array) $watch->meta, 'team_id' => $teamId, 'type' => $type];
        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * A team's id from its id, key or name.
     *
     * @throws WatchRefusedException when there is no such team
     */
    private function teamId(ConnectorCredential $credential, string $team): string
    {
        if (preg_match(self::UUID_PATTERN, $team) === 1) {
            return $team;
        }

        $found = $this->graphql($credential, 'query($filter: TeamFilter) { teams(filter: $filter, first: 1) { nodes { id } } }', ['filter' => ['or' => [['key' => ['eqIgnoreCase' => $team]], ['name' => ['eqIgnoreCase' => $team]]]]]);

        return data_get($found, 'teams.nodes.0.id') ?? throw new WatchRefusedException("Linear has no team \"{$team}\".");
    }

    /**
     * One GraphQL call as the watch's account.
     *
     * Linear answers a refusal with HTTP 200 and `errors`, so a refusal is read
     * from them. `$permissionHint` turns one into the sentence about the
     * `admin` scope, since creating a webhook is what needs it. `$tolerateErrors`
     * is for the calls that may fairly find nothing (a webhook that is gone).
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     *
     * @throws ConnectorException when the token or its permissions are refused
     */
    private function graphql(ConnectorCredential $credential, string $query, array $variables, bool $permissionHint = false, bool $tolerateErrors = false): array
    {
        $response = Http::withToken($this->tokens->accessToken($credential))->acceptJson()->timeout(20)
            ->post(self::API, ['query' => $query, 'variables' => $variables]);

        if ($response->status() === 401) {
            throw new ConnectorException('Linear refused the connected account. Reconnect it.');
        }

        $errors = (array) ($response->json('errors') ?? []);

        if ($errors !== [] && ! $tolerateErrors) {
            $code = (string) data_get($errors, '0.extensions.code', '');
            $message = (string) data_get($errors, '0.message', 'Unknown error');

            if (in_array($code, ['AUTHENTICATION_ERROR', 'FORBIDDEN'], true) || ($permissionHint && stripos($message, 'admin') !== false)) {
                throw new ConnectorException('Linear refused to register a webhook: the account needs to be connected with admin access. Reconnect it to get instant delivery.');
            }

            throw new RuntimeException("Linear API error: {$message}");
        }

        $response->throw();

        return $errors !== [] ? [] : (array) ($response->json('data') ?? []);
    }
}
