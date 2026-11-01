<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraGetIssueNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_get_issue';
    }

    public function name(): string
    {
        return 'Jira: Get Issue';
    }

    public function description(): string
    {
        return 'Gets one issue with its details, and optionally its comments, attachments and other fields.';
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
                'include_comments' => Field::boolean('Include comments'),
                'extra_fields' => Field::advanced(Field::text('Other fields', 'Comma-separated field ids to include as "custom_fields", e.g. customfield_10016.', 'customfield_10016')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $key = $this->issueKey($config);
        $extra = $this->csv($config['extra_fields'] ?? null);

        $issue = $this->request($run, $config, 'GET', '/issue/'.rawurlencode($key), ['fields' => implode(',', [self::ISSUE_FIELDS, 'attachment', ...$extra])]);
        $fields = (array) ($issue['fields'] ?? []);

        $shaped = $this->shapeIssue($issue, $this->site($run, $config)['url']);
        $shaped['attachments'] = array_values(array_map(fn (array $file): array => $this->shapeAttachment($file), array_filter((array) ($fields['attachment'] ?? []), 'is_array')));
        $shaped['custom_fields'] = array_combine($extra, array_map(fn (string $id): mixed => $this->simplify($fields[$id] ?? null), $extra)) ?: [];

        if (($config['include_comments'] ?? false) === true) {
            $comments = $this->listPages($run, $config, '/issue/'.rawurlencode($key).'/comment', ['orderBy' => 'created'], 'comments', 100);
            $shaped['comments'] = array_map($this->shapeComment(...), $comments);
        }

        return $shaped;
    }
}
