<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SalesforceUpdateOpportunityNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_update_opportunity';
    }

    public function name(): string
    {
        return 'Salesforce: Update Opportunity';
    }

    public function description(): string
    {
        return 'Changes a opportunity. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['record_id'],
            'properties' => [
                ...$this->credentialFields(),
                'record_id' => Field::text('Opportunity id', 'An id from a previous step.'),
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
        $id = $this->recordId($config);
        $fields = $this->writeFields($config, ['name' => 'Name', 'amount' => 'Amount', 'stage' => 'StageName', 'close_date' => 'CloseDate', 'account_id' => 'AccountId', 'probability' => 'Probability', 'type' => 'Type', 'description' => 'Description', 'owner_id' => 'OwnerId']);

        if ($fields === []) {
            throw new InvalidArgumentException('Change at least one field.');
        }

        $this->salesforce($run, $config, 'PATCH', "/sobjects/Opportunity/{$id}", [], $fields);

        return ['updated' => true, 'id' => $id, 'object' => 'Opportunity'];
    }
}
