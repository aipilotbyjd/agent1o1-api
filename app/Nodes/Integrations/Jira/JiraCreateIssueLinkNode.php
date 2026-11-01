<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraCreateIssueLinkNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_create_issue_link';
    }

    public function name(): string
    {
        return 'Jira: Create Issue Link';
    }

    public function description(): string
    {
        return 'Links two issues, e.g. one blocks the other or one duplicates the other.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue', 'link_type', 'other_issue'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'issue' => Field::text('Issue', 'The issue key, e.g. APP-123.', 'APP-123'),
                'link_type' => Field::dynamic('Link type', 'jira.link_types', 'The kind of link, e.g. Blocks, Duplicate or Relates.', 'Blocks'),
                'direction' => Field::select('This issue', ['outward' => 'does it to the other (e.g. blocks)', 'inward' => 'has it done to it (e.g. is blocked by)'], 'Which way the link reads.', 'outward'),
                'other_issue' => Field::text('Other issue', 'The key of the issue it is linked to.', 'APP-456'),
                'comment' => Field::advanced(Field::textarea('Comment', 'Added with the link.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $issue = $this->issueKey($config);
        $other = $this->issueKey($config, 'other_issue', 'The other issue');
        $inward = ($config['direction'] ?? 'outward') === 'inward';

        $this->request($run, $config, 'POST', '/issueLink', [], array_filter([
            'type' => ['name' => $this->requiredString($config, 'link_type', 'The link type')],
            'inwardIssue' => ['key' => $inward ? $issue : $other],
            'outwardIssue' => ['key' => $inward ? $other : $issue],
            'comment' => filled($config['comment'] ?? null) ? ['body' => JiraAdf::fromMarkdown((string) $config['comment'])] : null,
        ], fn (mixed $value): bool => $value !== null));

        return ['created' => true, 'issue' => $issue, 'other_issue' => $other, 'link_type' => (string) $config['link_type']];
    }
}
