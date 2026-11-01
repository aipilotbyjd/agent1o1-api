<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksMarkEstimateNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_mark_estimate';
    }

    public function name(): string
    {
        return 'Zoho Books: Mark Estimate Accepted or Declined';
    }

    public function description(): string
    {
        return 'Marks an estimate as sent, accepted or declined.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['estimate_id', 'status'],
            'properties' => [
                ...$this->credentialFields(),
                'estimate_id' => Field::text('Estimate id', 'An id from a previous step (a long number).'),
                'status' => Field::select('Mark as', ['sent' => 'Sent', 'accepted' => 'Accepted', 'declined' => 'Declined'], null, 'accepted'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'estimate_id', 'The estimate');
        $status = in_array($config['status'] ?? null, ['sent', 'accepted', 'declined'], true) ? $config['status'] : 'accepted';

        $this->books($run, $config, 'POST', "/estimates/{$id}/status/{$status}");

        return ['id' => $id, 'status' => $status];
    }
}
