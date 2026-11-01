<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SalesforceCreateOpportunityNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_create_opportunity';
    }

    public function name(): string
    {
        return 'Salesforce: Create Opportunity';
    }

    public function description(): string
    {
        return 'Creates a opportunity.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'stage', 'close_date'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'Acme annual plan'),
                'amount' => Field::text('Amount', 'A number.', '4800'),
                'stage' => Field::text('Stage', 'A stage of your org, e.g. Prospecting.', 'Prospecting'),
                'close_date' => Field::text('Close date', 'A day, like 2026-03-31.', '2026-03-31'),
                'account_id' => Field::advanced(Field::text('Account id', 'An id from a previous step.')),
                'probability' => Field::advanced(Field::text('Probability (%)')),
                'type' => Field::advanced(Field::text('Type', 'e.g. New Business.')),
                'description' => Field::advanced(Field::textarea('Description')),
                'owner_id' => $this->ownerField(),
                'fields' => $this->extraFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = $this->writeFields($config, ['name' => 'Name', 'amount' => 'Amount', 'stage' => 'StageName', 'close_date' => 'CloseDate', 'account_id' => 'AccountId', 'probability' => 'Probability', 'type' => 'Type', 'description' => 'Description', 'owner_id' => 'OwnerId']);
        $created = $this->salesforce($run, $config, 'POST', '/sobjects/Opportunity', [], $fields);

        return ['id' => $created['id'] ?? null, 'object' => 'Opportunity', 'success' => (bool) ($created['success'] ?? false)];
    }
}
