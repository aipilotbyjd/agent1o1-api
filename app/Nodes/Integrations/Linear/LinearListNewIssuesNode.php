<?php

namespace App\Nodes\Integrations\Linear;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class LinearListNewIssuesNode extends AbstractLinearNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * How far back a poll looks for issues made since it last ran.
     */
    private const int TRIGGER_WINDOW_MINUTES = 360;

    public function type(): string
    {
        return 'linear_list_new_issues';
    }

    public function name(): string
    {
        return 'Linear: List New Issues';
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
                ...$this->issueFilterFields(teamRequired: true),
                'created_within_minutes' => Field::advanced(Field::integer('Created within (minutes)', 'Only issues made this recently.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 10, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = now()->subMinutes((int) ($config['created_within_minutes'] ?? 1440))->toIso8601String();

        $issues = $this->listAll($run, $config, $this->issuesQuery('createdAt'), ['filter' => $this->issueFilter($run, $config, createdSince: $since) ?: null], 'issues', $this->limit($config, 10));

        return ['issues' => array_map($this->shapeIssue(...), $issues), 'count' => count($issues)];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'issues';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Linear: New issue';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when an issue is created in Linear.';
    }

    /**
     * Look back as far as the poll window, with a wide page.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'created_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => 250];
    }

    /**
     * Every minute: Linear has no webhook the app can register for a person.
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
}
