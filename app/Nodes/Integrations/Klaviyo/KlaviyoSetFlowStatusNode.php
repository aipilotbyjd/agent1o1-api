<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class KlaviyoSetFlowStatusNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_set_flow_status';
    }

    public function name(): string
    {
        return 'Klaviyo: Set Flow Status';
    }

    public function description(): string
    {
        return 'Turns a flow live, back to draft, or to manual (people are only added by hand).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['flow_id', 'status'],
            'properties' => [
                ...$this->credentialFields(),
                'flow_id' => Field::dynamic('Flow', 'klaviyo.flows', 'Pick a flow.', 'Welcome series'),
                'status' => Field::select('Status', ['live' => 'Live', 'draft' => 'Draft', 'manual' => 'Manual'], null, 'live'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->resourceId($config, 'flow_id', 'The flow');
        $status = (string) ($config['status'] ?? 'live');

        if (! in_array($status, ['live', 'draft', 'manual'], true)) {
            throw new InvalidArgumentException("\"{$status}\" is not a flow status. Use live, draft or manual.");
        }

        $this->klaviyo($run, $config, 'PATCH', "/flows/{$id}/", ['data' => ['type' => 'flow', 'id' => $id, 'attributes' => ['status' => $status]]]);

        return ['flow_id' => $id, 'status' => $status];
    }
}
