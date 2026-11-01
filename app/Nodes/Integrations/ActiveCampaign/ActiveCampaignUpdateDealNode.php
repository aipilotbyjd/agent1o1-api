<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ActiveCampaignUpdateDealNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_update_deal';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Update Deal';
    }

    public function description(): string
    {
        return 'Changes a deal: its title, value, stage, owner or whether it is open, won or lost. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['deal_id'],
            'properties' => [
                ...$this->credentialFields(),
                'deal_id' => Field::text('Deal id', null, '42'),
                'contact' => Field::advanced($this->contactField()),
                'status' => Field::select('Mark as', ['unchanged' => 'Leave as it is', 'open' => 'Open', 'won' => 'Won', 'lost' => 'Lost'], null, 'unchanged'),

                'title' => Field::text('Title', null, 'Acme annual plan'),
                'value' => Field::text('Value', 'The amount, e.g. 4900.00.', '4900.00'),
                'currency' => Field::text('Currency', 'Three-letter code, in lower case or upper.', 'usd'),
                'pipeline_id' => Field::advanced(Field::dynamic('Pipeline', 'activecampaign.pipelines', 'Pick a pipeline.', 'Sales', type: 'integer')),
                'stage_id' => Field::dynamic('Stage', 'activecampaign.stages', 'Pick a stage of the pipeline.', 'Qualified', dependsOn: ['pipeline_id'], type: 'integer'),
                'owner_id' => Field::advanced(Field::dynamic('Owner', 'activecampaign.users', null, 'Ada', type: 'integer')),
                'description' => Field::advanced(Field::textarea('Description')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'deal_id', 'The deal id');
        $body = $this->dealBody($run, $config, false);

        if ($body === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapeDeal((array) data_get((array) $this->ac($run, $config, 'PUT', "/deals/{$id}", ['deal' => $body]), 'deal', []));
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
            'contact' => filled($config['contact'] ?? null) ? $this->contactId($run, $config) : null,
            'title' => filled($config['title'] ?? null) ? (string) $config['title'] : null,
            'value' => filled($config['value'] ?? null) ? $this->cents($config['value']) : null,
            'currency' => filled($config['currency'] ?? null) ? strtolower((string) $config['currency']) : null,
            'group' => is_numeric($config['pipeline_id'] ?? null) ? (int) $config['pipeline_id'] : null,
            'stage' => is_numeric($config['stage_id'] ?? null) ? (int) $config['stage_id'] : null,
            'owner' => is_numeric($config['owner_id'] ?? null) ? (int) $config['owner_id'] : null,
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'status' => ['open' => 0, 'won' => 1, 'lost' => 2][(string) ($config['status'] ?? '')] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
