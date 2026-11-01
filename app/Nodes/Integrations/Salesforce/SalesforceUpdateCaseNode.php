<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SalesforceUpdateCaseNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_update_case';
    }

    public function name(): string
    {
        return 'Salesforce: Update Case';
    }

    public function description(): string
    {
        return 'Changes a case. Only what is filled in changes.';
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
                'record_id' => Field::text('Case id', 'An id from a previous step.'),
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
        $id = $this->recordId($config);
        $fields = $this->writeFields($config, ['subject' => 'Subject', 'description' => 'Description', 'status' => 'Status', 'priority' => 'Priority', 'origin' => 'Origin', 'contact_id' => 'ContactId', 'account_id' => 'AccountId', 'owner_id' => 'OwnerId']);

        if ($fields === []) {
            throw new InvalidArgumentException('Change at least one field.');
        }

        $this->salesforce($run, $config, 'PATCH', "/sobjects/Case/{$id}", [], $fields);

        return ['updated' => true, 'id' => $id, 'object' => 'Case'];
    }
}
