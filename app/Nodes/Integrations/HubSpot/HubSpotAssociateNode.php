<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class HubSpotAssociateNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_associate';
    }

    public function name(): string
    {
        return 'HubSpot: Link Records';
    }

    public function description(): string
    {
        return 'Links two records, e.g. a contact to a company, or a deal to a contact (HubSpot\'s default link).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['from_object', 'from_id', 'to_object', 'to_id'],
            'properties' => [
                ...$this->credentialFields(),
                'from_object' => Field::select('From', ['contacts' => 'Contact', 'companies' => 'Company', 'deals' => 'Deal', 'tickets' => 'Ticket'], null, 'contacts'),
                'from_id' => Field::text('From id', 'A number.'),
                'to_object' => Field::select('To', ['contacts' => 'Contact', 'companies' => 'Company', 'deals' => 'Deal', 'tickets' => 'Ticket'], null, 'companies'),
                'to_id' => Field::text('To id', 'A number.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $from = $this->objectOf($config, 'from_object');
        $to = $this->objectOf($config, 'to_object');
        $fromId = $this->numericId($config, 'from_id', 'The first record');
        $toId = $this->numericId($config, 'to_id', 'The second record');

        $this->hubspot($run, $config, 'PUT', "/crm/v4/objects/{$from}/{$fromId}/associations/default/{$to}/{$toId}");

        return ['linked' => true, 'from' => "{$from}/{$fromId}", 'to' => "{$to}/{$toId}"];
    }
}
