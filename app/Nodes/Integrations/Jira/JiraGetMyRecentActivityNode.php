<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraGetMyRecentActivityNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_get_my_recent_activity';
    }

    public function name(): string
    {
        return 'Jira: Get My Recent Activity';
    }

    public function description(): string
    {
        return 'Lists issues changed lately that the connected account is assigned to, reported, watches or viewed.';
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
                ...$this->siteFields(),

                'days' => Field::integer('Changed within (days)', null, 7, 1, 90),
                'limit' => Field::integer('Max results', null, 25, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $days = max(1, min(90, (int) ($config['days'] ?? 7)));
        $issues = $this->searchIssues($run, $config, "updated >= -{$days}d AND (assignee = currentUser() OR reporter = currentUser() OR watcher = currentUser() OR issuekey in issueHistory()) ORDER BY updated DESC", $this->limit($config, 25, 500));

        return ['issues' => $issues, 'count' => count($issues)];
    }
}
