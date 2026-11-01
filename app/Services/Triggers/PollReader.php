<?php

namespace App\Services\Triggers;

use App\Contracts\KeepsTriggerState;
use App\Contracts\SchedulesTriggerPolls;
use App\Contracts\SupportsTriggerMode;
use App\Exceptions\ConnectorException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Services\Connectors\ConnectorTokens;
use App\Services\Http\GuardedHttp;
use App\Services\Workflows\NodeRegistry;
use Carbon\CarbonInterface;
use Closure;
use RuntimeException;

/**
 * Reads the items a polling trigger sees right now, without recording or
 * starting anything.
 *
 * Items come from either:
 * - a plain endpoint, described by the trigger's config (`url`, `method`,
 *   `items_path`, `id_path`, `cursor_param`, `cursor_path`), sent with the
 *   connected account's token as a Bearer header when one is set; or
 * - a reader node switched on as a flow trigger (`config.node_type`), whose
 *   own execute() does the reading.
 *
 * Shared by PollTrigger (which fires runs for the new items) and
 * TriggerSampler (which only shows them).
 */
class PollReader
{
    public function __construct(
        private readonly GuardedHttp $http,
        private readonly ConnectorTokens $tokens,
        private readonly NodeRegistry $registry,
    ) {}

    /**
     * The trigger's items, oldest first, plus how to get each one's id,
     * payload and cursor value.
     *
     * The fifth element says when the poll should next look when the source
     * knows better than a fixed interval (see SchedulesTriggerPolls), else null.
     * The sixth is the state to keep for the next poll (see KeepsTriggerState),
     * or null for a trigger that keeps none.
     *
     * `$forSample` is for "Fetch sample": a trigger that keeps state returns
     * example items instead of reading what has changed, and never moves its state.
     *
     * @return array{0: list<array<string, mixed>>, 1: Closure(array<string, mixed>): string, 2: Closure(array<string, mixed>): array<string, mixed>, 3: Closure(array<string, mixed>): mixed, 4: Closure(): ?CarbonInterface, 5: Closure(): ?array<string, mixed>}
     */
    public function read(Trigger $trigger, bool $forSample = false): array
    {
        $config = array_merge((array) ($trigger->preset?->config ?? []), (array) $trigger->config);

        return isset($config['node_type'])
            ? $this->readerItems($trigger, $config, $forSample)
            : $this->urlItems($trigger, $config);
    }

    /**
     * Read items from a plain endpoint.
     *
     * Returns the items, plus how to get each one's id, payload and cursor
     * value.
     *
     * @param  array<string, mixed>  $config
     * @return array{0: list<array<string, mixed>>, 1: Closure(array<string, mixed>): string, 2: Closure(array<string, mixed>): array<string, mixed>, 3: Closure(array<string, mixed>): mixed, 4: Closure(): ?CarbonInterface, 5: Closure(): ?array<string, mixed>}
     */
    private function urlItems(Trigger $trigger, array $config): array
    {
        $url = $config['url'] ?? throw new RuntimeException("Polling trigger {$trigger->id} has no URL.");

        $response = $this->http->send(strtolower((string) ($config['method'] ?? 'get')), $url, headers: $this->authHeaders($trigger), query: $this->cursorQuery($trigger, $config));
        $response->throw();

        $items = array_values(array_filter((array) (data_get($response->json(), $config['items_path'] ?? null) ?? []), 'is_array'));

        // Runs start oldest first, so an endpoint that lists the newest item
        // first is turned around.
        if (($config['newest_first'] ?? false) === true) {
            $items = array_reverse($items);
        }

        $idPath = $config['id_path'] ?? 'id';

        return [
            $items,
            fn (array $item): string => (string) data_get($item, $idPath),
            fn (array $item): array => $item,
            fn (array $item): mixed => data_get($item, $config['cursor_path'] ?? $idPath),
            fn (): ?CarbonInterface => null,
            fn (): ?array => null,
        ];
    }

    /**
     * Read items through a reader node switched on as a flow trigger.
     *
     * The node's own execute() does the reading, as the trigger's owner,
     * through the node's connected account. Returns the same shape as
     * urlItems().
     *
     * @param  array<string, mixed>  $config
     * @return array{0: list<array<string, mixed>>, 1: Closure(array<string, mixed>): string, 2: Closure(array<string, mixed>): array<string, mixed>, 3: Closure(array<string, mixed>): mixed, 4: Closure(): ?CarbonInterface, 5: Closure(): ?array<string, mixed>}
     */
    private function readerItems(Trigger $trigger, array $config, bool $forSample = false): array
    {
        $node = $this->registry->resolve((string) $config['node_type']);

        if (! $node instanceof SupportsTriggerMode) {
            throw new RuntimeException("Node type [{$config['node_type']}] can't act as a trigger.");
        }

        $nodeConfig = (array) ($config['node_config'] ?? []);

        if ($trigger->credential_id !== null) {
            $nodeConfig['credential_id'] = $trigger->credential_id;
        }

        // Never saved: it only tells the node which workspace and person it
        // acts for, which is all a real run gives ResolvesConnectorCredential.
        $run = new Run(['workspace_id' => $trigger->workspace_id, 'triggered_by' => $trigger->actingUser()?->id]);

        if ($node instanceof KeepsTriggerState) {
            return $this->statefulItems($trigger, $node, $run, $node->triggerReadConfig($nodeConfig), $nodeConfig, $forSample);
        }

        $output = $node->execute($run, $node->triggerReadConfig($nodeConfig), []);
        $items = $node->triggerItems($output, $nodeConfig);

        // Runs start oldest first, so a burst is processed in the order it
        // happened.
        if ($node->triggerListsNewestFirst()) {
            $items = array_reverse($items);
        }

        return [
            $items,
            fn (array $item): string => $node->triggerItemId($item),
            fn (array $item): array => $node->expandTriggerItem($run, $nodeConfig, $item),
            fn (array $item): mixed => $node->triggerItemId($item),
            fn (): ?CarbonInterface => $node instanceof SchedulesTriggerPolls ? $node->nextTriggerPollAt($output, $nodeConfig) : null,
            fn (): ?array => null,
        ];
    }

    /**
     * Read what changed since the last poll through a node that keeps state.
     *
     * The state it returns is handed back (sixth element) for PollTrigger to
     * save once the poll has gone through; nothing is saved here, so a poll
     * that fails is repeated from the same state.
     *
     * @param  array<string, mixed>  $readConfig
     * @param  array<string, mixed>  $nodeConfig
     * @return array{0: list<array<string, mixed>>, 1: Closure(array<string, mixed>): string, 2: Closure(array<string, mixed>): array<string, mixed>, 3: Closure(array<string, mixed>): mixed, 4: Closure(): ?CarbonInterface, 5: Closure(): ?array<string, mixed>}
     */
    private function statefulItems(Trigger $trigger, SupportsTriggerMode&KeepsTriggerState $node, Run $run, array $readConfig, array $nodeConfig, bool $forSample): array
    {
        if ($forSample) {
            $items = $node->triggerSampleItems($run, $readConfig);
            $nextState = null;
        } else {
            // State taken under other settings (another sheet, another id
            // column) says nothing about the new ones: reading it would report
            // everything that differs as gone. Start from a fresh baseline.
            $fingerprint = md5(json_encode($nodeConfig, JSON_THROW_ON_ERROR));
            $previous = $trigger->poll_cursor['state'] ?? null;
            $previous = is_array($previous) && ($previous['_config'] ?? null) === $fingerprint ? array_diff_key($previous, ['_config' => true]) : null;

            ['items' => $items, 'state' => $nextState] = $node->pollSince($run, $readConfig, $previous);
            $nextState['_config'] = $fingerprint;
        }

        return [
            array_values($items),
            fn (array $item): string => $node->triggerItemId($item),
            fn (array $item): array => $node->expandTriggerItem($run, $nodeConfig, $item),
            fn (array $item): mixed => $node->triggerItemId($item),
            fn (): ?CarbonInterface => null,
            fn (): ?array => $nextState,
        ];
    }

    /**
     * The Authorization header for the trigger's connected account, if any.
     *
     * Only an account the trigger's owner could pick themselves: a team
     * account or their own personal one. TriggerNodeSync checks this at
     * publish; it's checked again here in case the account changed since.
     *
     * @return array<string, string>
     *
     * @throws ConnectorException when the account can't produce a token
     */
    private function authHeaders(Trigger $trigger): array
    {
        if ($trigger->credential_id === null) {
            return [];
        }

        $credential = ConnectorCredential::query()
            ->where('workspace_id', $trigger->workspace_id)
            ->find($trigger->credential_id);

        $owner = $trigger->actingUser();

        if ($credential === null || ($owner !== null && ! $credential->isVisibleTo($owner))) {
            throw new ConnectorException("Polling trigger {$trigger->id} has no usable account.");
        }

        return ['Authorization' => 'Bearer '.$this->tokens->accessToken($credential)];
    }

    /**
     * The query parameter that asks the endpoint for items after the last
     * one seen, when the trigger sets `cursor_param`.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function cursorQuery(Trigger $trigger, array $config): array
    {
        $cursorParam = $config['cursor_param'] ?? null;
        $cursorValue = $trigger->poll_cursor['value'] ?? null;

        if ($cursorParam === null || $cursorValue === null) {
            return [];
        }

        return [$cursorParam => $cursorValue];
    }
}
