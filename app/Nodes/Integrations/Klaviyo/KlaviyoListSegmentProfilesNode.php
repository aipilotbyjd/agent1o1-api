<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KlaviyoListSegmentProfilesNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_list_segment_profiles';
    }

    public function name(): string
    {
        return 'Klaviyo: List Segment Profiles';
    }

    public function description(): string
    {
        return 'Lists the profiles currently in a segment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['segment_id'],
            'properties' => [
                ...$this->credentialFields(),
                'segment_id' => Field::dynamic('Segment', 'klaviyo.segments', 'Pick a segment.', 'Big spenders'),
                'limit' => Field::integer('Max results', null, 50, 1, 10000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->resourceId($config, 'segment_id', 'The segment');
        [$profiles] = $this->listAll($run, $config, "/segments/{$id}/profiles/", [], $this->limit($config, 50, 10000), 100);

        return ['profiles' => array_map($this->shapeProfile(...), $profiles), 'count' => count($profiles), 'segment_id' => $id];
    }
}
