<?php

namespace App\Nodes\Integrations\Postmark;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PostmarkGetBounceNode extends AbstractPostmarkNode
{
    public function type(): string
    {
        return 'postmark_get_bounce';
    }

    public function name(): string
    {
        return 'Postmark: Get Bounce';
    }

    public function description(): string
    {
        return 'Reads one bounce.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['bounce_id'],
            'properties' => [
                ...$this->credentialFields(),
                'bounce_id' => Field::text('Bounce id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeBounce((array) $this->postmark($run, $config, 'GET', '/bounces/'.$this->pathPart($config, 'bounce_id', 'The bounce id')));
    }
}
