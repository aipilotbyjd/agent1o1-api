<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoListNotesNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_list_notes';
    }

    public function name(): string
    {
        return 'Zoho CRM: List Notes';
    }

    public function description(): string
    {
        return 'Lists the notes on a record.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['module', 'record_id'],
            'properties' => [
                ...$this->credentialFields(),
                'module' => Field::dynamic('On a', 'zoho.modules', null, 'Leads', allowCustom: true),
                'record_id' => Field::text('Record id', 'An id from a previous step.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $module = $this->apiName((string) ($config['module'] ?? ''));
        $id = $this->recordId($config);
        $response = $this->zohoResponse($run, $config, 'GET', "/{$module}/{$id}/Notes", ['fields' => 'Note_Title,Note_Content,Created_Time,Owner']);

        if ($response->status() !== 204) {
            $this->assertOk($response);
        }

        $notes = array_values(array_filter((array) ($response->json('data') ?? []), 'is_array'));

        return ['notes' => array_map(fn (array $note): array => ['id' => isset($note['id']) ? (string) $note['id'] : null, 'title' => $note['Note_Title'] ?? null, 'content' => $note['Note_Content'] ?? null, 'created_at' => $note['Created_Time'] ?? null, 'author' => data_get($note, 'Owner.name')], $notes), 'count' => count($notes)];
    }
}
