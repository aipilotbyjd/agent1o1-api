<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Tickets that were opened, changed or answered by the customer lately, as a reader
 * and as a trigger. Desk lists tickets newest first by creation or change time, so a
 * poll reads the newest pages and keeps those within the look-back. A webhook
 * (`ZohoDeskSource`) makes the poll look at once; the poll's own dedupe decides
 * what runs.
 */
abstract class AbstractZohoDeskTicketTriggerNode extends AbstractZohoDeskNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * The ticket time this node is about: `createdTime`, `modifiedTime` or `customerResponseTime`.
     */
    abstract protected function stamp(): string;

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
                'department_id' => Field::advanced($this->departmentField()),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only tickets this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc();
        $stamp = $this->stamp();
        $order = $stamp === 'createdTime' ? 'createdTime' : 'modifiedTime';

        $tickets = $this->listAll($run, $config, '/tickets', [
            'sortBy' => '-'.$order,
            'departmentId' => $this->optionalId($config, 'department_id', 'The department'),
            'include' => 'contacts,assignee',
        ], 500);

        $tickets = array_values(array_filter($tickets, fn (array $ticket): bool => filled($ticket[$stamp] ?? null) && Carbon::parse((string) $ticket[$stamp])->greaterThanOrEqualTo($since)));
        $tickets = array_slice($tickets, 0, $this->limit($config, 50));

        return ['tickets' => array_map($this->shapeTicket(...), $tickets), 'count' => count($tickets)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'tickets';
    }

    /**
     * A ticket that changes again, or is answered again, is a new event; one that is opened once is not.
     */
    public function triggerItemId(array $item): string
    {
        return match ($this->stamp()) {
            'createdTime' => (string) ($item['id'] ?? ''),
            'modifiedTime' => ($item['id'] ?? '').':'.($item['updated_at'] ?? ''),
            default => ($item['id'] ?? '').':'.($item['customer_replied_at'] ?? ''),
        };
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'changed_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => 1000];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
