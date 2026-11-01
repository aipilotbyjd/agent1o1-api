<?php

namespace App\Nodes\Integrations\Postmark;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PostmarkActivateBounceNode extends AbstractPostmarkNode
{
    public function type(): string
    {
        return 'postmark_activate_bounce';
    }

    public function name(): string
    {
        return 'Postmark: Reactivate Bounced Address';
    }

    public function description(): string
    {
        return 'Lets Postmark send to an address again after a bounce deactivated it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        $result = (array) $this->postmark($run, $config, 'PUT', '/bounces/'.$this->pathPart($config, 'bounce_id', 'The bounce id').'/activate', []);

        return ['activated' => true, ...$this->shapeBounce((array) ($result['Bounce'] ?? []))];
    }
}
