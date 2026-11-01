<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class LinearGetIssueNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_get_issue';
    }

    public function name(): string
    {
        return 'Linear: Get Issue';
    }

    public function description(): string
    {
        return 'Gets one issue by its identifier (ENG-123) or id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue'],
            'properties' => [
                ...$this->credentialFields(),
                'issue' => Field::text('Issue', 'The identifier, e.g. ENG-123, or the issue id.', 'ENG-123'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $issue = $this->requiredString($config, 'issue', 'The issue');

        $found = $this->graphql($run, $config, 'query($id: String!) { issue(id: $id) { '.self::ISSUE_FIELDS.' } }', ['id' => $issue])['issue'] ?? null;

        return is_array($found) ? $this->shapeIssue($found) : throw new RuntimeException("Linear has no issue \"{$issue}\".");
    }
}
