<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailchimpListSegmentsNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_list_segments';
    }

    public function name(): string
    {
        return 'Mailchimp: List Segments';
    }

    public function description(): string
    {
        return 'Lists an audience\'s segments and tags with their member counts.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->audienceField(),
                'type' => Field::select('Kind', ['any' => 'Any', 'static' => 'Tags and static segments', 'saved' => 'Saved segments', 'fuzzy' => 'Automatic'], null, 'any'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $type = (string) ($config['type'] ?? 'any');
        $segments = $this->listAll($run, $config, '/lists/'.$this->listId($config).'/segments', 'segments', ['type' => $type !== 'any' && $type !== '' ? $type : null], 1000);

        return ['segments' => array_map(fn (array $segment): array => ['id' => $segment['id'] ?? null, 'name' => $segment['name'] ?? null, 'type' => $segment['type'] ?? null, 'member_count' => $segment['member_count'] ?? null, 'created_at' => $segment['created_at'] ?? null], $segments), 'count' => count($segments)];
    }
}
