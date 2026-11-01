<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SalesforceCreateTaskNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_create_task';
    }

    public function name(): string
    {
        return 'Salesforce: Create Task';
    }

    public function description(): string
    {
        return 'Creates a task.';
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
                'subject' => Field::text('Subject', null, 'Call Ada about the quote'),
                'status' => Field::advanced(Field::text('Status', 'e.g. Not Started, Completed.')),
                'priority' => Field::advanced(Field::text('Priority', 'e.g. Normal, High.')),
                'due_date' => Field::advanced(Field::text('Due date', 'A day, like 2026-03-31.')),
                'who_id' => Field::advanced(Field::text('About person (lead or contact id)')),
                'what_id' => Field::advanced(Field::text('About record (account or opportunity id)')),
                'description' => Field::advanced(Field::textarea('Description')),
                'owner_id' => $this->ownerField('Assigned to'),
                'fields' => $this->extraFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = $this->writeFields($config, ['subject' => 'Subject', 'status' => 'Status', 'priority' => 'Priority', 'due_date' => 'ActivityDate', 'who_id' => 'WhoId', 'what_id' => 'WhatId', 'description' => 'Description', 'owner_id' => 'OwnerId']);
        $created = $this->salesforce($run, $config, 'POST', '/sobjects/Task', [], $fields);

        return ['id' => $created['id'] ?? null, 'object' => 'Task', 'success' => (bool) ($created['success'] ?? false)];
    }
}
