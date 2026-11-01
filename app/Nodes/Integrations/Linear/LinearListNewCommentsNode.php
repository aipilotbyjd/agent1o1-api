<?php

namespace App\Nodes\Integrations\Linear;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class LinearListNewCommentsNode extends AbstractLinearNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * How far back a poll looks for comments made since it last ran.
     */
    private const int TRIGGER_WINDOW_MINUTES = 360;

    public function type(): string
    {
        return 'linear_list_new_comments';
    }

    public function name(): string
    {
        return 'Linear: List New Comments';
    }

    public function description(): string
    {
        return 'Lists the comments made lately, newest first. As a trigger it starts the flow for each new comment.';
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
                'team' => Field::dynamic('Team', 'linear.teams', 'Only comments on issues of this team. Leave empty for every team.', 'Engineering'),
                'created_within_minutes' => Field::advanced(Field::integer('Made within (minutes)', 'Only comments written this recently.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 25, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $teamId = $this->resolveId($run, $config, 'team', $config['team'] ?? null);

        $filter = ['createdAt' => ['gte' => now()->subMinutes((int) ($config['created_within_minutes'] ?? 1440))->toIso8601String()]];

        if ($teamId !== null) {
            $filter['issue'] = ['team' => ['id' => ['eq' => $teamId]]];
        }

        $comments = $this->listAll(
            $run,
            $config,
            'query($filter: CommentFilter, $first: Int, $after: String) { comments(filter: $filter, first: $first, after: $after, orderBy: createdAt) { nodes { '.self::COMMENT_FIELDS.' } pageInfo { hasNextPage endCursor } } }',
            ['filter' => $filter],
            'comments',
            $this->limit($config, 25),
        );

        return ['comments' => array_map($this->shapeComment(...), $comments), 'count' => count($comments)];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'comments';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Linear: New comment';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when someone comments on a Linear issue.';
    }

    /**
     * Look back as far as the poll window, with a wide page.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'created_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => 250];
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
}
