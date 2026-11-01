<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Models\Agents\Agent;
use App\Models\Ai\ModelCatalog;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Workflows\Workflow;
use App\Services\Timezones\Timezones;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\OptionsSource;
use Illuminate\Database\Eloquent\Builder;

/**
 * Choices read from this app rather than a connected account: the
 * workspace's agents and (visible) workflows, and the public model catalog:
 * the same entries `ModelCatalogController` lists. Also backs the trigger
 * nodes' pickers: connected accounts and timezones.
 */
class WorkspaceOptions implements OptionsSource
{
    private const int PAGE_SIZE = 50;

    public function sources(): array
    {
        return [
            'workspace.agents',
            'workspace.workflows',
            'ai.models',
            'workspace.connector_credentials',
            'system.timezones',
        ];
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return match ($source) {
            'ai.models' => $this->models($query),
            'workspace.connector_credentials' => $this->connectorCredentials($query),
            'system.timezones' => $this->timezones($query),
            'workspace.agents' => $this->named($query, Agent::query()->where('workspace_id', $query->workspace->id)),
            default => $this->named($query, Workflow::query()->visible()->where('workspace_id', $query->workspace->id)),
        };
    }

    private function models(NodeOptionsQuery $query): NodeOptionsPage
    {
        $models = ModelCatalog::query()
            ->where('is_active', true)
            ->where('is_internal', false)
            ->when($query->search, fn (Builder $models, string $search) => $models->whereLike('display_name', $this->containing($search)))
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get(['slug', 'display_name', 'brand']);

        return new NodeOptionsPage($models->map(fn (ModelCatalog $model): NodeOption => new NodeOption(
            $model->slug,
            $model->display_name,
            $model->brand,
        )));
    }

    /**
     * The workspace's team accounts plus the member's own personal ones: the
     * same set they could pick on any integration node.
     */
    private function connectorCredentials(NodeOptionsQuery $query): NodeOptionsPage
    {
        $credentials = ConnectorCredential::query()
            ->with('connector:id,name')
            ->where('workspace_id', $query->workspace->id)
            ->visibleTo($query->user)
            ->orderBy('name')
            ->get(['id', 'connector_id', 'name', 'account_label'])
            ->filter(fn (ConnectorCredential $credential): bool => $query->matches($credential->name, (string) $credential->account_label));

        return new NodeOptionsPage($credentials->map(fn (ConnectorCredential $credential): NodeOption => new NodeOption(
            (string) $credential->id,
            $credential->name,
            trim(($credential->connector?->name ?? '').' '.($credential->account_label ?? '')) ?: null,
        ))->values());
    }

    private function timezones(NodeOptionsQuery $query): NodeOptionsPage
    {
        return new NodeOptionsPage(collect(Timezones::options())
            ->filter(fn (array $timezone): bool => $query->matches($timezone['label']))
            ->map(fn (array $timezone): NodeOption => new NodeOption($timezone['id'], $timezone['label']))
            ->values());
    }

    /**
     * Alphabetical, offset-paged. Fetches one extra row to know whether
     * there's a next page without a count query.
     *
     * @param  Builder<Agent>|Builder<Workflow>  $records
     */
    private function named(NodeOptionsQuery $query, Builder $records): NodeOptionsPage
    {
        $offset = $query->cursor !== null && ctype_digit($query->cursor) ? (int) $query->cursor : 0;

        $page = $records
            ->when($query->search, fn (Builder $records, string $search) => $records->whereLike('name', $this->containing($search)))
            ->orderBy('name')
            ->orderBy('id')
            ->offset($offset)
            ->limit(self::PAGE_SIZE + 1)
            ->get(['id', 'name', 'description']);

        return new NodeOptionsPage(
            $page->take(self::PAGE_SIZE)->map(fn (Agent|Workflow $record): NodeOption => new NodeOption(
                (string) $record->id,
                $record->name,
                $record->description,
            )),
            $page->count() > self::PAGE_SIZE ? $offset + self::PAGE_SIZE : null,
        );
    }

    /**
     * A LIKE pattern matching `$search` literally: `%` and `_` typed by the
     * member aren't wildcards.
     */
    private function containing(string $search): string
    {
        return '%'.addcslashes($search, '\\%_').'%';
    }
}
