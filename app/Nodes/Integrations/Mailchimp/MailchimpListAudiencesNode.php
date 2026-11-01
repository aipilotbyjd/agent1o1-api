<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailchimpListAudiencesNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_list_audiences';
    }

    public function name(): string
    {
        return 'Mailchimp: List Audiences';
    }

    public function description(): string
    {
        return 'Lists the account\'s audiences with their member counts.';
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
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $lists = $this->listAll($run, $config, '/lists', 'lists', ['sort_field' => 'date_created', 'sort_dir' => 'DESC'], $this->limit($config, 50));

        return ['audiences' => array_map($this->shapeList(...), $lists), 'count' => count($lists)];
    }
}
