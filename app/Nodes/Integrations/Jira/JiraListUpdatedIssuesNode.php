<?php

namespace App\Nodes\Integrations\Jira;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class JiraListUpdatedIssuesNode extends AbstractJiraNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * How far back a poll looks for issues since it last ran.
     */
    private const int TRIGGER_WINDOW_MINUTES = 360;

    public function type(): string
    {
        return 'jira_list_updated_issues';
    }

    public function name(): string
    {
        return 'Jira: List Updated Issues';
    }

    public function description(): string
    {
        return 'Lists the issues changed lately, most recently changed first. As a trigger it starts the flow each time an issue is changed (not when it is made).';
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
                'window_minutes' => Field::advanced(Field::integer('Changed within (minutes)', 'Only issues changed this recently.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 10, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $jql = $this->withClause($this->filterJql($run, $config), 'updated >= -'.max(1, (int) ($config['window_minutes'] ?? 1440)).'m', 'updated DESC');
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
     * An issue edited this soon after it was made is the creation itself, not
     * a change to it.
     */
    private const int CREATION_GRACE_SECONDS = 30;

    /**
     * Issues changed after they were made.
     */
    public function triggerItems(array $output, array $config): array
    {
        return array_values(array_filter(
            (array) ($output['issues'] ?? []),
            fn (mixed $issue): bool => is_array($issue) && isset($issue['created_at'], $issue['updated_at'])
                && Carbon::parse((string) $issue['created_at'])->diffInSeconds(Carbon::parse((string) $issue['updated_at']), true) > self::CREATION_GRACE_SECONDS,
        ));
    }

    /**
     * An issue is a new item each time it changes: its key and last change.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['key']) ? $item['key'].':'.($item['updated_at'] ?? '') : '';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Jira: Issue updated';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when an existing Jira issue is changed.';
    }
}
