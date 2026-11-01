<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class PipedriveMarkDealNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_mark_deal';
    }

    public function name(): string
    {
        return 'Pipedrive: Mark Deal Won or Lost';
    }

    public function description(): string
    {
        return 'Closes a deal as won or lost (with a reason), or opens it again.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['record_id', 'status'],
            'properties' => [
                ...$this->credentialFields(),
                'record_id' => Field::text('Deal id', 'The id from a previous step (a number).'),
                'status' => Field::select('Mark as', ['won' => 'Won', 'lost' => 'Lost', 'open' => 'Open again'], null, 'won'),
                'lost_reason' => Field::advanced(Field::text('Reason lost')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'record_id', 'The deal');
        $status = (string) ($config['status'] ?? 'won');

        if (! in_array($status, ['won', 'lost', 'open'], true)) {
            throw new InvalidArgumentException("\"{$status}\" is not a deal status.");
        }

        $deal = (array) ($this->pipedrive($run, $config, 'PUT', "/deals/{$id}", [], ['status' => $status, 'lost_reason' => $status === 'lost' && filled($config['lost_reason'] ?? null) ? trim((string) $config['lost_reason']) : null])['data'] ?? []);

        return ['id' => $deal['id'] ?? $id, 'status' => $deal['status'] ?? $status, 'won_time' => $deal['won_time'] ?? null, 'lost_time' => $deal['lost_time'] ?? null];
    }
}
