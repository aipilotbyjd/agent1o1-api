<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ResendListAudiencesNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_list_audiences';
    }

    public function name(): string
    {
        return 'Resend: List Audiences';
    }

    public function description(): string
    {
        return 'Lists the audiences (groups of contacts).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = (array) $this->resend($run, $config, 'GET', '/audiences');
        $audiences = array_map(fn (array $audience): array => ['id' => $audience['id'] ?? null, 'name' => $audience['name'] ?? null, 'created_at' => $audience['created_at'] ?? null], array_values(array_filter((array) ($found['data'] ?? []), 'is_array')));

        return ['audiences' => $audiences, 'count' => count($audiences)];
    }
}
