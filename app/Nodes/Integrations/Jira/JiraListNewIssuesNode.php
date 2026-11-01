<?php

namespace App\Nodes\Integrations\Jira;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class JiraListNewIssuesNode extends AbstractJiraNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * How far back a poll looks for issues since it last ran.
     */
    private const int TRIGGER_WINDOW_MINUTES = 360;

    public function type(): string
    {
        return 'jira_list_new_issues';
    }

    public function name(): string
    {
        return 'Jira: List New Issues';
    }

    public function description(): string
    {
        return 'Lists the issues created lately, newest first. As a trigger it starts the flow for each new issue.';
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

                ...$this->issueFilterFields(),
                'window_minutes' => Field::advanced(Field::integer('Created within (minutes)', 'Only issues made this recently.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 10, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $jql = $this->withClause($this->filterJql($run, $config), 'created >= -'.max(1, (int) ($config['window_minutes'] ?? 1440)).'m', 'created DESC');
        $issues = $this->searchIssues($run, $config, $jql, $this->limit($config, 10, 500));

        return ['issues' => $issues, 'count' => count($issues)];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'issues';
    }

    /**
     * Look back as far as the poll window, with a wide page.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'window_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => 100];
    }

    /**
     * Every minute.
     */
    public function triggerPollMinutes(): int
    {
        return 1;
    }

    /**
     * Newest first, as the list is ordered.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Jira: New issue';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when an issue is created in Jira.';
    }
}
