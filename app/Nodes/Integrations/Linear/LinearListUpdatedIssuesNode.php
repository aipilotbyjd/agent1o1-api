<?php

namespace App\Nodes\Integrations\Linear;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class LinearListUpdatedIssuesNode extends AbstractLinearNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * An issue edited this soon after it was made is the creation itself, not
     * a change to it.
     */
    private const int CREATION_GRACE_SECONDS = 30;

    /**
     * How far back a poll looks for changes since it last ran.
     */
    private const int TRIGGER_WINDOW_MINUTES = 360;

    public function type(): string
    {
        return 'linear_list_updated_issues';
    }

    public function name(): string
    {
        return 'Linear: List Updated Issues';
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
                ...$this->issueFilterFields(teamRequired: true),
                'updated_within_minutes' => Field::advanced(Field::integer('Changed within (minutes)', 'Only issues changed this recently.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 10, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = now()->subMinutes((int) ($config['updated_within_minutes'] ?? 1440))->toIso8601String();

        $issues = $this->listAll($run, $config, $this->issuesQuery('updatedAt'), ['filter' => $this->issueFilter($run, $config, updatedSince: $since) ?: null], 'issues', $this->limit($config, 10));

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
     * Issues changed after they were made.
     */
    public function triggerItems(array $output, array $config): array
    {
        return array_values(array_filter(
            (array) ($output['issues'] ?? []),
            fn (mixed $issue): bool => is_array($issue) && $this->secondsEdited($issue) > self::CREATION_GRACE_SECONDS,
        ));
    }

    /**
     * An issue is a new item each time it changes: its id and last change.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['id']) ? $item['id'].':'.($item['updated_at'] ?? '') : '';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Linear: Issue updated';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when an existing Linear issue is changed.';
    }

    /**
     * Look back as far as the poll window, with a wide page.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'updated_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => 250];
    }

    /**
     * Every minute.
     */
    public function triggerPollMinutes(): int
    {
        return 1;
    }

    /**
     * Newest first, as the list is ordered by when issues changed.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * Seconds between an issue's creation and its last change.
     *
     * @param  array<string, mixed>  $issue
     */
    private function secondsEdited(array $issue): int
    {
        if (! isset($issue['created_at'], $issue['updated_at'])) {
            return 0;
        }

        return max(0, (int) Carbon::parse((string) $issue['created_at'])->diffInSeconds(Carbon::parse((string) $issue['updated_at']), true));
    }
}
