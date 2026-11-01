<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ActiveCampaignCreateDealNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_create_deal';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Create Deal';
    }

    public function description(): string
    {
        return 'Creates a deal in a pipeline stage for a contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title', 'contact', 'pipeline_id', 'stage_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact' => $this->contactField(),

                'title' => Field::text('Title', null, 'Acme annual plan'),
                'value' => Field::text('Value', 'The amount, e.g. 4900.00.', '4900.00'),
                'currency' => Field::text('Currency', 'Three-letter code, in lower case or upper.', 'usd'),
                'pipeline_id' => Field::dynamic('Pipeline', 'activecampaign.pipelines', 'Pick a pipeline.', 'Sales', type: 'integer'),
                'stage_id' => Field::dynamic('Stage', 'activecampaign.stages', 'Pick a stage of the pipeline.', 'Qualified', dependsOn: ['pipeline_id'], type: 'integer'),
                'owner_id' => Field::advanced(Field::dynamic('Owner', 'activecampaign.users', null, 'Ada', type: 'integer')),
                'description' => Field::advanced(Field::textarea('Description')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeDeal((array) data_get((array) $this->ac($run, $config, 'POST', '/deals', ['deal' => $this->dealBody($run, $config, true)]), 'deal', []));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function dealBody(Run $run, array $config, bool $creating): array
    {
        return array_filter([
            'contact' => $creating || filled($config['contact'] ?? null) ? $this->contactId($run, $config) : null,
            'title' => $creating || filled($config['title'] ?? null) ? $this->requiredString($config, 'title', 'The title') : null,
            'value' => filled($config['value'] ?? null) ? $this->cents($config['value']) : ($creating ? 0 : null),
            'currency' => filled($config['currency'] ?? null) ? strtolower((string) $config['currency']) : ($creating ? 'usd' : null),
            'group' => $creating || filled($config['pipeline_id'] ?? null) ? $this->numericId($config, 'pipeline_id', 'The pipeline') : null,
            'stage' => $creating || filled($config['stage_id'] ?? null) ? $this->numericId($config, 'stage_id', 'The stage') : null,
            'owner' => is_numeric($config['owner_id'] ?? null) ? (int) $config['owner_id'] : null,
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'status' => $creating ? 0 : (['open' => 0, 'won' => 1, 'lost' => 2][(string) ($config['status'] ?? '')] ?? null),
        ], fn (mixed $value): bool => $value !== null);
    }
}
