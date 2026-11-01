<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoUpdateTaskNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_update_task';
    }

    public function name(): string
    {
        return 'Zoho CRM: Update Task';
    }

    public function description(): string
    {
        return 'Changes a task. Only what is filled in changes.';
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
                'record_id' => Field::text('Task id', 'An id from a previous step.'),
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
        $id = $this->recordId($config);
        $fields = $this->writeFields($config, ['subject' => 'Subject', 'due_date' => 'Due_Date', 'status' => 'Status', 'priority' => 'Priority', 'description' => 'Description', 'who_id' => 'Who_Id', 'what_id' => 'What_Id', 'owner_id' => 'Owner'], ['Who_Id', 'What_Id', 'Owner']);

        if (isset($fields['What_Id'])) {
            $fields['$se_module'] = in_array($config['what_module'] ?? 'Accounts', ['Accounts', 'Deals', 'Leads'], true) ? ($config['what_module'] ?? 'Accounts') : 'Accounts';
        }

        if ($fields === []) {
            throw new InvalidArgumentException('Change at least one field.');
        }

        $this->written($this->zoho($run, $config, 'PUT', "/Tasks/{$id}", [], ['data' => [$fields]]));

        return ['updated' => true, 'id' => $id, 'module' => 'Tasks'];
    }
}
