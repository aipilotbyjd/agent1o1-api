<?php

use App\Enums\Setup\DatasetState;
use App\Enums\Setup\Ownership;
use App\Enums\Setup\SyncStatus;
use App\Models\Ai\ModelCatalog;
use App\Models\Billing\Plan;
use App\Models\Connectors\Connector;
use App\Models\Nodes\NodeCategory;
use App\Models\System\SetupSync;
use App\Models\Templates\WorkflowTemplate;
use App\Models\Triggers\TriggerPreset;
use App\Models\User;
use App\Services\Setup\Datasets\ConnectorsDataset;
use App\Services\Setup\SetupDataset;
use App\Services\Setup\SetupRegistry;
use App\Services\Setup\SetupSyncer;
use App\Services\Workflows\GraphValidator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/**
 * @param  list<string>  $dependencies
 */
function fakeSetupDataset(string $key, array $dependencies = [], ?Closure $apply = null, array $problems = []): SetupDataset
{
    return new class($key, $dependencies, $apply, $problems) implements SetupDataset
    {
        public int $applied = 0;

        public function __construct(private string $key, private array $dependencies, private ?Closure $apply, public array $problems = []) {}

        public function key(): string
        {
            return $this->key;
        }

        public function label(): string
        {
            return $this->key;
        }

        public function ownership(): Ownership
        {
            return Ownership::CodeOwned;
        }

        public function version(): int
        {
            return 1;
        }

        public function dependencies(): array
        {
            return $this->dependencies;
        }

        public function sources(): array
        {
            return [];
        }

        public function minimumRows(): int
        {
            return 0;
        }

        public function rows(): int
        {
            return $this->applied;
        }

        public function problems(): array
        {
            return $this->problems;
        }

        public function apply(): void
        {
            $this->applied++;

            if ($this->apply !== null) {
                ($this->apply)();
            }
        }
    };
}

it('syncs every dataset on an empty database', function () {
    $this->artisan('setup:catalog:sync')->assertSuccessful();

    expect(NodeCategory::query()->where('kind', 'core')->count())->toBe(6)
        ->and(NodeCategory::query()->where('kind', 'app')->count())->toBeGreaterThan(0)
        ->and(Connector::query()->count())->toBeGreaterThan(0)
        ->and(TriggerPreset::query()->count())->toBeGreaterThanOrEqual(6)
        ->and(Plan::query()->count())->toBeGreaterThan(0)
        ->and(SetupSync::query()->where('status', SyncStatus::Applied)->count())->toBe(count(app(SetupRegistry::class)->all()));
});

it('skips datasets that are already in sync without writing', function () {
    $this->artisan('setup:catalog:sync')->assertSuccessful();

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $results = app(SetupSyncer::class)->sync();

    expect(collect($results)->every(fn ($result): bool => $result->status === SyncStatus::Unchanged))->toBeTrue()
        ->and(collect($queries)->filter(fn (string $sql): bool => preg_match('/^(insert|update|delete)/i', $sql) === 1))->toBeEmpty();
});

it('re-applies a dataset whose rows were deleted', function () {
    $this->artisan('setup:catalog:sync')->assertSuccessful();

    TriggerPreset::query()->delete();

    $results = collect(app(SetupSyncer::class)->sync())->keyBy('dataset');

    expect($results['trigger-presets']->status)->toBe(SyncStatus::Applied)
        ->and(TriggerPreset::query()->count())->toBeGreaterThanOrEqual(6);
});

it('re-applies everything with --force', function () {
    $this->artisan('setup:catalog:sync')->assertSuccessful();

    $results = app(SetupSyncer::class)->sync(force: true);

    expect(collect($results)->every(fn ($result): bool => $result->status === SyncStatus::Applied))->toBeTrue();
});

it('leaves admin edits to plans alone', function () {
    $this->artisan('setup:catalog:sync')->assertSuccessful();

    $plan = Plan::query()->firstOrFail();
    $plan->update(['name' => 'Renamed by an admin']);

    app(SetupSyncer::class)->sync(force: true);

    expect($plan->fresh()->name)->toBe('Renamed by an admin');
});

it('writes nothing on a dry run', function () {
    $this->artisan('setup:catalog:sync --dry-run')->assertSuccessful();

    expect(NodeCategory::query()->count())->toBe(0)
        ->and(SetupSync::query()->count())->toBe(0);
});

it('syncs the dependencies of a single dataset', function () {
    $this->artisan('setup:catalog:sync --only=connectors')->assertSuccessful();

    expect(NodeCategory::query()->where('kind', 'core')->count())->toBe(6)
        ->and(Connector::query()->count())->toBeGreaterThan(0)
        ->and(Plan::query()->count())->toBe(0);
});

it('rejects an unknown dataset', function () {
    $this->artisan('setup:catalog:sync --only=nope')->assertFailed();
});

it('orders datasets by their dependencies', function () {
    $registry = new SetupRegistry([
        fakeSetupDataset('c', ['b']),
        fakeSetupDataset('b', ['a']),
        fakeSetupDataset('a'),
    ]);

    expect(array_keys($registry->all()))->toBe(['a', 'b', 'c']);
});

it('refuses a dependency loop and a missing dependency', function () {
    expect(fn () => (new SetupRegistry([fakeSetupDataset('a', ['b']), fakeSetupDataset('b', ['a'])]))->all())
        ->toThrow(LogicException::class)
        ->and(fn () => (new SetupRegistry([fakeSetupDataset('a', ['ghost'])]))->all())
        ->toThrow(LogicException::class);
});

it('records a failed dataset and skips what depends on it', function () {
    $broken = fakeSetupDataset('broken', apply: fn () => throw new RuntimeException('boom'));
    $child = fakeSetupDataset('child', ['broken']);
    $independent = fakeSetupDataset('independent');

    $syncer = new SetupSyncer(new SetupRegistry([$broken, $child, $independent]));
    $results = collect($syncer->sync())->keyBy('dataset');

    expect($results['broken']->status)->toBe(SyncStatus::Failed)
        ->and($results['broken']->message)->toBe('boom')
        ->and($results['child']->status)->toBe(SyncStatus::Skipped)
        ->and($results['independent']->status)->toBe(SyncStatus::Applied)
        ->and($child->applied)->toBe(0)
        ->and($syncer->inspect($broken))->toBe(DatasetState::Failed);
});

it('reports status and fails until everything is synced', function () {
    $this->artisan('setup:catalog:status')->assertFailed();

    $this->artisan('setup:catalog:sync')->assertSuccessful();

    $this->artisan('setup:catalog:status')->assertSuccessful();
});

it('seeds setup data only and creates no users', function () {
    $this->seed(DatabaseSeeder::class);

    expect(NodeCategory::query()->count())->toBeGreaterThan(0)
        ->and(User::query()->count())->toBe(0);
});

it('keeps an admin\'s model switches when the code is re-applied', function () {
    $this->artisan('setup:catalog:sync')->assertSuccessful();

    $model = ModelCatalog::query()->where('slug', 'claude-sonnet-5')->firstOrFail();
    $model->forceFill(['is_active' => false])->save();
    $route = $model->routes()->orderBy('priority')->firstOrFail();
    $route->update(['is_enabled' => false, 'priority' => 9]);

    app(SetupSyncer::class)->sync(force: true);

    expect($model->fresh()->is_active)->toBeFalse()
        ->and($route->fresh()->is_enabled)->toBeFalse()
        ->and($route->fresh()->priority)->toBe(9);
});

it('reads the starting plans\' Stripe ids from config, not env', function () {
    config(['billing.stripe_plan_ids.STRIPE_PLAN_STARTER_MONTHLY_PRICE_ID' => 'price_from_config']);

    $this->syncSetup('plans');

    expect(Plan::query()->where('slug', 'starter')->value('stripe_price_id_monthly'))->toBe('price_from_config');
});

it('has no seeder classes besides DatabaseSeeder', function () {
    expect(glob(database_path('seeders/*.php')))->toBe([database_path('seeders/DatabaseSeeder.php')])
        ->and(is_dir(database_path('seeders/Setup')))->toBeFalse();
});

it('refuses to write a dataset whose definitions have problems', function () {
    $bad = fakeSetupDataset('bad', problems: ['Connector [x] has no app category with the same slug.']);
    $child = fakeSetupDataset('child', ['bad']);

    $syncer = new SetupSyncer(new SetupRegistry([$bad, $child]));
    $results = collect($syncer->sync())->keyBy('dataset');

    expect($results['bad']->status)->toBe(SyncStatus::Failed)
        ->and($results['bad']->message)->toContain('no app category')
        ->and($bad->applied)->toBe(0)
        ->and($results['child']->status)->toBe(SyncStatus::Skipped)
        ->and(SetupSync::query()->where('dataset', 'bad')->value('error'))->toContain('no app category');
});

it('also validates on a dry run', function () {
    $bad = fakeSetupDataset('bad', problems: ['Duplicate key.']);

    $results = (new SetupSyncer(new SetupRegistry([$bad])))->sync(dryRun: true);

    expect($results[0]->status)->toBe(SyncStatus::Failed)
        ->and($results[0]->message)->toBe('Duplicate key.')
        ->and(SetupSync::query()->count())->toBe(0);
});

it('has no problems in any of the shipped definitions', function () {
    foreach (app(SetupRegistry::class)->all() as $key => $dataset) {
        expect($dataset->problems())->toBe([], "Dataset [{$key}] has problems");
    }
});

it('catches a connector with no app category and a duplicate key', function () {
    $connectors = require database_path('setup/connectors.php');
    $connectors[] = [...$connectors[0], 'key' => 'ghost-app'];
    $connectors[] = $connectors[0];

    $problems = app(ConnectorsDataset::class)->check($connectors);

    expect($problems)->toContain('Connector [ghost-app] has no app category with the same slug.')
        ->and(implode(' ', $problems))->toContain('appears more than once');
});

it('keeps all setup data in database/setup, not in the dataset classes', function () {
    foreach (['plans', 'node-categories', 'app-node-categories', 'trigger-presets', 'connectors', 'model-catalog', 'agent-templates', 'workflow-templates', 'page-templates', 'template-collections'] as $name) {
        expect(is_file(database_path("setup/{$name}.php")))->toBeTrue("database/setup/{$name}.php is missing");
    }
});

it('ships real workflow templates in every business domain, each a valid graph', function () {
    $this->syncSetup('workflow-templates');

    $templates = WorkflowTemplate::query()->whereNull('workspace_id')->get();

    expect($templates)->not->toBeEmpty()
        ->and($templates->every(fn (WorkflowTemplate $template): bool => $template->isPublic()))->toBeTrue()
        ->and($templates->pluck('category')->unique()->count())->toBeGreaterThanOrEqual(10);

    foreach ($templates as $template) {
        expect(app(GraphValidator::class)->validate($template->graph['nodes'], $template->graph['edges']))
            ->toBe([], "Workflow template [{$template->slug}] has an invalid graph");
    }
});

it('keeps workflow templates idempotent across syncs', function () {
    $this->syncSetup('workflow-templates');
    $first = WorkflowTemplate::query()->whereNull('workspace_id')->count();

    $this->syncSetup('workflow-templates');

    expect(WorkflowTemplate::query()->whereNull('workspace_id')->count())->toBe($first);
});

it('gives automatic workflow templates settings instead of run-form inputs', function () {
    $templates = require database_path('setup/workflow-templates.php');

    $automatic = collect($templates)->filter(fn (array $template): bool => collect($template['graph']['nodes'])
        ->contains(fn (array $node): bool => str_ends_with($node['type'], '_trigger')));

    expect($automatic)->not->toBeEmpty();

    foreach ($automatic as $slug => $template) {
        expect(collect($template['graph']['nodes'])->pluck('type'))->not->toContain('workflow_input', "[{$slug}] starts on its own, so nobody can fill in a run form");
    }
});
