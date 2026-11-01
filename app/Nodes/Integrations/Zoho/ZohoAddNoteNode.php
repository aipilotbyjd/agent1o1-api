<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoAddNoteNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_add_note';
    }

    public function name(): string
    {
        return 'Zoho CRM: Add Note';
    }

    public function description(): string
    {
        return 'Adds a note to a lead, contact, account, deal or other record.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['module', 'record_id', 'content'],
            'properties' => [
                ...$this->credentialFields(),
                'module' => Field::dynamic('On a', 'zoho.modules', null, 'Leads', allowCustom: true),
                'record_id' => Field::text('Record id', 'An id from a previous step.'),
                'title' => Field::advanced(Field::text('Title')),
                'content' => Field::textarea('Note'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $module = $this->apiName((string) ($config['module'] ?? ''));
        $id = $this->recordId($config);

        $row = $this->written($this->zoho($run, $config, 'POST', '/Notes', [], ['data' => [array_filter([
            'Note_Title' => filled($config['title'] ?? null) ? trim((string) $config['title']) : null,
            'Note_Content' => $this->requiredString($config, 'content', 'The note'),
            'Parent_Id' => ['id' => $id, 'module' => ['api_name' => $module]],
        ], fn (mixed $value): bool => $value !== null)]]));

        return ['id' => data_get($row, 'details.id'), 'module' => $module, 'record_id' => $id];
    }
}
