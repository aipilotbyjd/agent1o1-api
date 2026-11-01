<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class SendGridSearchContactsNode extends AbstractSendGridNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'sendgrid_search_contacts';
    }

    public function name(): string
    {
        return 'SendGrid: Search Contacts';
    }

    public function description(): string
    {
        return 'Finds marketing contacts that match a query, on a list, or created lately. As a trigger it starts the flow for each new contact.';
    }

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
                'query' => Field::advanced(Field::text('Query', "SendGrid's contact query, e.g. email LIKE 'ada%' AND country = 'US'.")),
                'list_id' => Field::advanced($this->listField('On list')),
                'created_after' => Field::advanced(Field::datetime('Created after')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $parts = [];

        if (filled($config['query'] ?? null)) {
            $parts[] = '('.trim((string) $config['query']).')';
        }

        if (filled($config['list_id'] ?? null)) {
            $parts[] = "CONTAINS(list_ids, '".$this->identifier($config, 'list_id', 'The list')."')";
        }

        if (filled($config['created_after'] ?? null)) {
            $parts[] = "created_at > TIMESTAMP '".Carbon::parse((string) $config['created_after'])->utc()->format('Y-m-d\\TH:i:s\\Z')."'";
        }

        if ($parts === []) {
            throw new InvalidArgumentException('Give a query, a list or a creation time to search by.');
        }

        $found = (array) $this->sendgrid($run, $config, 'POST', '/marketing/contacts/search', ['query' => implode(' AND ', $parts)]);
        $contacts = array_slice(array_values(array_filter((array) ($found['result'] ?? []), 'is_array')), 0, $this->limit($config, 50, 1000));

        return ['contacts' => array_map($this->shapeContact(...), $contacts), 'count' => count($contacts), 'total' => $found['contact_count'] ?? count($contacts)];
    }

    private const int TRIGGER_WINDOW_MINUTES = 360;

    protected function triggerItemsPath(): ?string
    {
        return 'contacts';
    }

    public function triggerName(): string
    {
        return 'SendGrid: New contact';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a marketing contact is added in SendGrid. Checks every few minutes.';
    }

    /**
     * Those created in the look-back window.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'query' => null, 'created_after' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'limit' => 1000];
    }
}
