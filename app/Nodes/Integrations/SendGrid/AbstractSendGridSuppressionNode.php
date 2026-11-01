<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * One of SendGrid's suppression lists (bounces, blocks, spam reports, invalid
 * addresses, global unsubscribes), read newest first, optionally since a time.
 * As a trigger it starts a flow for each address that lands on the list.
 *
 * SendGrid has no webhook that can be registered per trigger, so this polls.
 * The id is the address with the time it was suppressed, so an address that
 * is removed and suppressed again is a new event.
 */
abstract class AbstractSendGridSuppressionNode extends AbstractSendGridNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * The path under /suppression, e.g. `bounces`.
     */
    abstract protected function group(): string;

    /**
     * The key the list is returned under, e.g. `bounces`.
     */
    abstract protected function resultKey(): string;

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'since' => Field::advanced(Field::datetime('Since', 'Only addresses suppressed after this time.')),
                'limit' => Field::integer('Max results', null, 50, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = (array) $this->sendgrid($run, $config, 'GET', '/suppression/'.$this->group(), query: [
            'start_time' => filled($config['since'] ?? null) ? Carbon::parse((string) $config['since'])->utc()->timestamp : null,
            'limit' => $this->limit($config, 50),
        ]);

        $entries = array_map(fn (array $row): array => [
            'email' => $row['email'] ?? null,
            'reason' => $row['reason'] ?? null,
            'status' => $row['status'] ?? null,
            'ip' => $row['ip'] ?? null,
            'created' => $row['created'] ?? null,
            'created_at' => isset($row['created']) ? Carbon::createFromTimestampUTC((int) $row['created'])->toIso8601String() : null,
        ], array_values(array_filter($rows, 'is_array')));

        usort($entries, fn (array $a, array $b): int => ($b['created'] ?? 0) <=> ($a['created'] ?? 0));

        return [$this->resultKey() => $entries, 'count' => count($entries)];
    }

    protected function triggerItemsPath(): ?string
    {
        return $this->resultKey();
    }

    public function triggerItemId(array $item): string
    {
        return ($item['email'] ?? '').':'.($item['created'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'since' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'limit' => 500];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
