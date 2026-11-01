<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class JiraTransitionIssueNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_transition_issue';
    }

    public function name(): string
    {
        return 'Jira: Transition Issue';
    }

    public function description(): string
    {
        return 'Moves an issue to another status (To Do, In Progress, Done...), with an optional comment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue', 'transition'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'issue' => Field::text('Issue', 'The issue key, e.g. APP-123.', 'APP-123'),
                'transition' => Field::dynamic('Move to', 'jira.transitions', 'The transition or the status it leads to, e.g. In Progress or Done.', 'Done', uses: ['issue']),
                'comment' => Field::textarea('Comment', 'Added with the move. Markdown is supported.'),
                'resolution' => Field::advanced(Field::text('Resolution', 'For a move that closes the issue: e.g. Done or Won\'t Do.', 'Done')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $key = $this->issueKey($config);
        $wanted = $this->requiredString($config, 'transition', 'The transition');

        $available = array_values(array_filter((array) ($this->request($run, $config, 'GET', '/issue/'.rawurlencode($key).'/transitions')['transitions'] ?? []), 'is_array'));

        $match = collect($available)->first(fn (array $transition): bool => (string) $transition['id'] === $wanted)
            ?? collect($available)->first(fn (array $transition): bool => strcasecmp((string) $transition['name'], $wanted) === 0)
            ?? collect($available)->first(fn (array $transition): bool => strcasecmp((string) data_get($transition, 'to.name'), $wanted) === 0);

        if (! is_array($match)) {
            $names = implode(', ', array_map(fn (array $transition): string => (string) $transition['name'], $available));

            throw new InvalidArgumentException("{$key} can't move to \"{$wanted}\" from its current status. It can: ".($names === '' ? 'nothing' : $names).'.');
        }

        $this->request($run, $config, 'POST', '/issue/'.rawurlencode($key).'/transitions', [], array_filter([
            'transition' => ['id' => (string) $match['id']],
            'fields' => filled($config['resolution'] ?? null) ? ['resolution' => ['name' => (string) $config['resolution']]] : null,
            'update' => filled($config['comment'] ?? null) ? ['comment' => [['add' => ['body' => JiraAdf::fromMarkdown((string) $config['comment'])]]]] : null,
        ], fn (mixed $value): bool => $value !== null));

        return ['key' => $key, 'transition_id' => (string) $match['id'], 'transition' => $match['name'] ?? null, 'status' => data_get($match, 'to.name'), 'url' => $this->site($run, $config)['url']."/browse/{$key}"];
    }
}
