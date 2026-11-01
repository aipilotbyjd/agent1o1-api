<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraGetIssueLinksNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_get_issue_links';
    }

    public function name(): string
    {
        return 'Jira: Get Issue Links';
    }

    public function description(): string
    {
        return 'Lists the issues linked to an issue, and how.';
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
                ...$this->siteFields(),
                'issue' => Field::text('Issue', 'The issue key, e.g. APP-123.', 'APP-123'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->request($run, $config, 'GET', '/issue/'.rawurlencode($this->issueKey($config)), ['fields' => 'issuelinks']);

        $links = [];

        foreach ((array) data_get($answer, 'fields.issuelinks', []) as $link) {
            $outward = isset($link['outwardIssue']);
            $other = (array) ($link[$outward ? 'outwardIssue' : 'inwardIssue'] ?? []);

            $links[] = [
                'id' => $link['id'] ?? null,
                'type' => data_get($link, 'type.name'),
                'relationship' => data_get($link, 'type.'.($outward ? 'outward' : 'inward')),
                'direction' => $outward ? 'outward' : 'inward',
                'issue_key' => $other['key'] ?? null,
                'issue_summary' => data_get($other, 'fields.summary'),
                'issue_status' => data_get($other, 'fields.status.name'),
            ];
        }

        return ['links' => $links, 'count' => count($links)];
    }
}
