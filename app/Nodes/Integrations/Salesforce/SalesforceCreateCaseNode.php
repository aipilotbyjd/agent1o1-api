<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SalesforceCreateCaseNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_create_case';
    }

    public function name(): string
    {
        return 'Salesforce: Create Case';
    }

    public function description(): string
    {
        return 'Creates a case.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['subject'],
            'properties' => [
                ...$this->credentialFields(),
                'subject' => Field::text('Subject', null, 'Cannot log in'),
                'description' => Field::textarea('Description'),
                'status' => Field::advanced(Field::text('Status', 'e.g. New, Working, Closed.')),
                'priority' => Field::advanced(Field::text('Priority', 'e.g. Low, Medium, High.')),
                'origin' => Field::advanced(Field::text('Origin', 'e.g. Web, Phone, Email.')),
                'contact_id' => Field::advanced(Field::text('Contact id', 'An id from a previous step.')),
                'account_id' => Field::advanced(Field::text('Account id', 'An id from a previous step.')),
                'owner_id' => $this->ownerField(),
                'fields' => $this->extraFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = $this->writeFields($config, ['subject' => 'Subject', 'description' => 'Description', 'status' => 'Status', 'priority' => 'Priority', 'origin' => 'Origin', 'contact_id' => 'ContactId', 'account_id' => 'AccountId', 'owner_id' => 'OwnerId']);
        $created = $this->salesforce($run, $config, 'POST', '/sobjects/Case', [], $fields);

        return ['id' => $created['id'] ?? null, 'object' => 'Case', 'success' => (bool) ($created['success'] ?? false)];
    }
}
