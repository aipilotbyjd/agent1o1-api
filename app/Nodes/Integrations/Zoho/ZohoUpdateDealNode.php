<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoUpdateDealNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_update_deal';
    }

    public function name(): string
    {
        return 'Zoho CRM: Update Deal';
    }

    public function description(): string
    {
        return 'Changes a deal. Only what is filled in changes.';
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
                'record_id' => Field::text('Deal id', 'An id from a previous step.'),
                'name' => Field::text('Name', null, 'Acme annual plan'),
                'stage' => Field::text('Stage', 'A stage of your org, e.g. Qualification.', 'Qualification'),
                'close_date' => Field::text('Closing date', 'A day, like 2026-03-31.', '2026-03-31'),
                'amount' => Field::advanced(Field::text('Amount', 'A number.')),
                'account_id' => Field::advanced(Field::text('Account id', 'An id from a previous step.')),
                'contact_id' => Field::advanced(Field::text('Contact id', 'An id from a previous step.')),
                'pipeline' => Field::advanced(Field::text('Pipeline')),
                'source' => Field::advanced(Field::text('Lead source')),
                'description' => Field::advanced(Field::textarea('Description')),
                'owner_id' => $this->ownerField(),
                'fields' => $this->extraFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->recordId($config);
        $fields = $this->writeFields($config, ['name' => 'Deal_Name', 'stage' => 'Stage', 'close_date' => 'Closing_Date', 'amount' => 'Amount', 'account_id' => 'Account_Name', 'contact_id' => 'Contact_Name', 'pipeline' => 'Pipeline', 'source' => 'Lead_Source', 'description' => 'Description', 'owner_id' => 'Owner'], ['Account_Name', 'Contact_Name', 'Owner']);

        if ($fields === []) {
            throw new InvalidArgumentException('Change at least one field.');
        }

        $this->written($this->zoho($run, $config, 'PUT', "/Deals/{$id}", [], ['data' => [$fields]]));

        return ['updated' => true, 'id' => $id, 'module' => 'Deals'];
    }
}
