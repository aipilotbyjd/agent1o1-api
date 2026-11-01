<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoCreateDealNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_create_deal';
    }

    public function name(): string
    {
        return 'Zoho CRM: Create Deal';
    }

    public function description(): string
    {
        return 'Creates a deal.';
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
        $fields = $this->writeFields($config, ['name' => 'Deal_Name', 'stage' => 'Stage', 'close_date' => 'Closing_Date', 'amount' => 'Amount', 'account_id' => 'Account_Name', 'contact_id' => 'Contact_Name', 'pipeline' => 'Pipeline', 'source' => 'Lead_Source', 'description' => 'Description', 'owner_id' => 'Owner'], ['Account_Name', 'Contact_Name', 'Owner']);

        $row = $this->written($this->zoho($run, $config, 'POST', '/Deals', [], ['data' => [$fields]]));

        return ['id' => data_get($row, 'details.id'), 'module' => 'Deals', 'created_at' => data_get($row, 'details.Created_Time')];
    }
}
