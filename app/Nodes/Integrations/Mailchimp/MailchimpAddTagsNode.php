<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MailchimpAddTagsNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_add_tags';
    }

    public function name(): string
    {
        return 'Mailchimp: Add Tags to Member';
    }

    public function description(): string
    {
        return 'Puts tags on a person (the tags are created if new).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'email', 'tags'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->audienceField(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'tags' => Field::list('Tags', 'One or more tag names.', 'customer'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tags = $this->csv($config['tags'] ?? null);

        if ($tags === []) {
            throw new InvalidArgumentException('Give at least one tag.');
        }

        $this->mailchimp($run, $config, 'POST', $this->memberPath($config).'/tags', ['tags' => array_map(fn (string $tag): array => ['name' => $tag, 'status' => 'active'], $tags)]);

        return ['tagged' => true, 'email' => $this->email($config), 'tags' => $tags];
    }
}
