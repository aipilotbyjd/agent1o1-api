<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailchimpCreateSegmentNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_create_segment';
    }

    public function name(): string
    {
        return 'Mailchimp: Create Segment';
    }

    public function description(): string
    {
        return 'Creates a tag (a static segment), optionally with some people in it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->audienceField(),
                'name' => Field::text('Name', null, 'VIP customers'),
                'emails' => Field::list('People to put in it', 'Email addresses.', 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $emails = array_map(fn (string $email): string => $this->email(['e' => $email], 'e'), $this->csv($config['emails'] ?? null));
        $segment = (array) $this->mailchimp($run, $config, 'POST', '/lists/'.$this->listId($config).'/segments', ['name' => $this->requiredString($config, 'name', 'The name'), 'static_segment' => $emails]);

        return ['id' => $segment['id'] ?? null, 'name' => $segment['name'] ?? null, 'member_count' => $segment['member_count'] ?? count($emails)];
    }
}
