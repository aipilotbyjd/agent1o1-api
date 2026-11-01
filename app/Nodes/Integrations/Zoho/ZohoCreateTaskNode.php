<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoCreateTaskNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_create_task';
    }

    public function name(): string
    {
        return 'Zoho CRM: Create Task';
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
                'due_date' => Field::advanced(Field::text('Due date', 'A day, like 2026-03-31.')),
                'status' => Field::advanced(Field::text('Status', 'e.g. Not Started, Completed.')),
                'priority' => Field::advanced(Field::text('Priority', 'e.g. High, Normal.')),
                'description' => Field::advanced(Field::textarea('Description')),
                'who_id' => Field::advanced(Field::text('About person (contact or lead id)')),
                'what_id' => Field::advanced(Field::text('About record (account or deal id)')),
                'what_module' => Field::advanced(Field::select('The record is a', ['Accounts' => 'Account', 'Deals' => 'Deal', 'Leads' => 'Lead'], 'Needed with "About record".', 'Accounts')),
                'owner_id' => $this->ownerField('Assigned to'),
                'fields' => $this->extraFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = $this->writeFields($config, ['subject' => 'Subject', 'due_date' => 'Due_Date', 'status' => 'Status', 'priority' => 'Priority', 'description' => 'Description', 'who_id' => 'Who_Id', 'what_id' => 'What_Id', 'owner_id' => 'Owner'], ['Who_Id', 'What_Id', 'Owner']);

        if (isset($fields['What_Id'])) {
            $fields['$se_module'] = in_array($config['what_module'] ?? 'Accounts', ['Accounts', 'Deals', 'Leads'], true) ? ($config['what_module'] ?? 'Accounts') : 'Accounts';
        }

        $row = $this->written($this->zoho($run, $config, 'POST', '/Tasks', [], ['data' => [$fields]]));

        return ['id' => data_get($row, 'details.id'), 'module' => 'Tasks', 'created_at' => data_get($row, 'details.Created_Time')];
    }
}
