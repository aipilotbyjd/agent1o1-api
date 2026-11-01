<?php

namespace App\Services\SuperAdmin\Catalog;

use App\Models\Connectors\Connector;
use App\Models\Nodes\NodeCategory;
use App\Models\Runs\NodeRun;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\DB;

/**
 * What is wrong or stale in the catalog, found by looking, so nobody has to
 * remember to check: missing descriptions, apps no one can use, icons shared by
 * two apps, connectors that fail their test, apps nobody has run in a month.
 */
class SuperAdminCatalogHealthService
{
    public const UNUSED_AFTER_DAYS = 30;

    public function __construct(private readonly NodeRegistry $registry) {}

    /**
     * @return array{summary: array{apps: int, warnings: int, info: int}, issues: list<array<string, mixed>>}
     */
    public function report(): array
    {
        $apps = NodeCategory::query()->where('kind', 'app')->orderBy('sort_order')->orderBy('name')->get();
        $connectors = Connector::query()->get();
        $connectorsByKey = $connectors->keyBy('key');
        $nodes = collect($this->registry->catalog())->groupBy('category');
        $recentTypes = NodeRun::query()->where('created_at', '>=', now()->subDays(self::UNUSED_AFTER_DAYS))->distinct()->pluck('type')->flip();
        $failing = DB::table('connector_credentials')
            ->whereNull('deleted_at')
            ->where('last_test_ok', false)
            ->groupBy('connector_id')
            ->selectRaw('connector_id, count(*) as failing')
            ->pluck('failing', 'connector_id');

        $issues = [];
        $add = function (string $code, string $severity, ?NodeCategory $app, string $message) use (&$issues): void {
            $issues[] = [
                'code' => $code,
                'severity' => $severity,
                'app' => $app === null ? null : ['id' => $app->id, 'slug' => $app->slug, 'name' => $app->name],
                'message' => $message,
            ];
        };

        foreach ($apps as $app) {
            $appNodes = $nodes->get($app->slug, collect());
            $connector = $connectorsByKey->get($app->slug);

            if (blank($app->description)) {
                $add('no_description', 'warning', $app, 'It has no description, so the editor shows nothing under its name.');
            }

            if (($app->functions ?? []) === [] || in_array('other', $app->functions, true)) {
                $add('no_function', 'warning', $app, 'It is not filed under any function, so it shows under "Other apps".');
            }

            if ($appNodes->isEmpty()) {
                $add('no_nodes', 'warning', $app, 'It has no nodes, so there is nothing to add from it.');
            }

            if ($connector === null && $appNodes->contains('requires_connector', true)) {
                $add('no_connector', 'warning', $app, 'Its nodes need an account, but it has no connector to connect one.');
            }

            if ($connector !== null && ! $connector->is_active && $connector->is_featured) {
                $add('off_but_featured', 'warning', $app, 'It is switched off but still marked as featured.');
            }

            if ($connector !== null && ($failing[$connector->id] ?? 0) > 0) {
                $count = (int) $failing[$connector->id];
                $add('failing_credentials', 'warning', $app, $count === 1 ? 'One saved account fails its connection test.' : "{$count} saved accounts fail their connection test.");
            }

            if ($appNodes->isNotEmpty() && ($connector === null || $connector->is_active) && ! $appNodes->pluck('type')->contains(fn (string $type): bool => $recentTypes->has($type))) {
                $add('unused', 'info', $app, 'No node from it has run in the last '.self::UNUSED_AFTER_DAYS.' days.');
            }

            if ($app->status !== 'operational') {
                $add('status_set', 'info', $app, 'It is marked as '.$app->status.($app->status_note ? ': '.$app->status_note : '.'));
            }
        }

        foreach ($apps->filter(fn (NodeCategory $app): bool => filled($app->icon))->groupBy('icon')->filter(fn ($group): bool => $group->count() > 1) as $icon => $group) {
            $add('duplicate_icon', 'info', $group->first(), 'The icon "'.$icon.'" is shared by '.$group->pluck('name')->join(', ', ' and ').'.');
        }

        $appSlugs = $apps->pluck('slug')->all();

        foreach ($connectors->reject(fn (Connector $connector): bool => in_array($connector->key, $appSlugs, true)) as $connector) {
            $add('connector_without_app', 'info', null, 'The connector "'.$connector->name.'" has no matching app, so it is not listed in the editor.');
        }

        $counts = collect($issues)->countBy('severity');

        return [
            'summary' => ['apps' => $apps->count(), 'warnings' => (int) $counts->get('warning', 0), 'info' => (int) $counts->get('info', 0)],
            'issues' => collect($issues)->sortBy(fn (array $issue): int => $issue['severity'] === 'warning' ? 0 : 1)->values()->all(),
        ];
    }
}
