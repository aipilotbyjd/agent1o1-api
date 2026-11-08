<?php

use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Folder;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowVersion;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;

/*
 * The workspace list endpoints used to load every row in one response. They
 * now page by cursor: each request is capped, and walking the cursors visits
 * every row exactly once, even when many rows share a `created_at`.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->base = "/api/v1/workspaces/{$this->workspace->id}";

    Passport::actingAs($this->owner);

    /**
     * Follows `meta.next_cursor` until the list ends, returning every id seen
     * and the number of requests it took.
     *
     * @return array{ids: list<string>, requests: int}
     */
    $this->walk = function (string $url, int $perPage): array {
        $ids = [];
        $requests = 0;
        $cursor = null;

        do {
            $response = $this->getJson($url.(str_contains($url, '?') ? '&' : '?').http_build_query(array_filter(['per_page' => $perPage, 'cursor' => $cursor])))
                ->assertOk()
                ->assertJsonPath('success', true);

            $requests++;
            array_push($ids, ...array_column($response->json('data'), 'id'));
            $cursor = $response->json('meta.next_cursor');

            expect($response->json('meta.per_page'))->toBe($perPage)
                ->and(count($response->json('data')))->toBeLessThanOrEqual($perPage);
        } while ($cursor !== null && $requests < 50);

        return ['ids' => $ids, 'requests' => $requests];
    };
});

describe('workflows', function () {
    it('pages through every workflow once, even when they share a timestamp', function () {
        $sameInstant = Carbon::parse('2026-01-01 12:00:00');
        $workflows = Workflow::factory()->forWorkspace($this->workspace)->count(7)->create(['created_at' => $sameInstant]);

        ['ids' => $ids, 'requests' => $requests] = ($this->walk)("{$this->base}/workflows", 3);

        expect($requests)->toBe(3)
            ->and($ids)->toHaveCount(7)
            ->and(array_unique($ids))->toHaveCount(7)
            ->and($ids)->toEqualCanonicalizing($workflows->pluck('id')->all());
    });

    it('reports where the list ends', function () {
        Workflow::factory()->forWorkspace($this->workspace)->count(3)->create();

        $this->getJson("{$this->base}/workflows?per_page=2")
            ->assertOk()
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonCount(2, 'data');

        $this->getJson("{$this->base}/workflows?per_page=5")
            ->assertOk()
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('meta.next_cursor', null)
            ->assertJsonCount(3, 'data');
    });

    it('caps per_page so one request cannot load a whole workspace', function () {
        Workflow::factory()->forWorkspace($this->workspace)->count(3)->create();

        $this->getJson("{$this->base}/workflows?per_page=100000")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    });

    it('enriches each page with node types and last run time for its own workflows', function () {
        $first = Workflow::factory()->forWorkspace($this->workspace)->create(['created_at' => now()->subMinute()]);
        $second = Workflow::factory()->forWorkspace($this->workspace)->create(['created_at' => now()]);
        $first->nodes()->create(['key' => 'a', 'type' => 'slack.send_message', 'config' => []]);
        $second->nodes()->create(['key' => 'a', 'type' => 'http.request', 'config' => []]);
        Run::factory()->forWorkflow($first)->completed()->create();

        $pageOne = $this->getJson("{$this->base}/workflows?per_page=1")->assertOk();
        $pageTwo = $this->getJson("{$this->base}/workflows?per_page=1&cursor=".$pageOne->json('meta.next_cursor'))->assertOk();

        expect($pageOne->json('data.0.id'))->toBe($second->id)
            ->and($pageOne->json('data.0.node_types'))->toBe(['http.request'])
            ->and($pageOne->json('data.0.last_run_at'))->toBeNull()
            ->and($pageTwo->json('data.0.id'))->toBe($first->id)
            ->and($pageTwo->json('data.0.node_types'))->toBe(['slack.send_message'])
            ->and($pageTwo->json('data.0.last_run_at'))->not->toBeNull();
    });

    it('runs a fixed number of queries however many workflows exist', function () {
        Workflow::factory()->forWorkspace($this->workspace)->count(3)->create();
        $this->getJson("{$this->base}/workflows?per_page=2")->assertOk(); // warm up lazy singletons

        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson("{$this->base}/workflows?per_page=2")->assertOk();

            return count(DB::getQueryLog());
        };

        $before = $count();
        Workflow::factory()->forWorkspace($this->workspace)->count(20)->create();

        expect($count())->toBe($before);
    });

    it('pages the trash too', function () {
        $trashed = Workflow::factory()->forWorkspace($this->workspace)->count(4)->create();
        $trashed->each->delete();
        Workflow::factory()->forWorkspace($this->workspace)->create();

        ['ids' => $ids, 'requests' => $requests] = ($this->walk)("{$this->base}/workflows/trash", 3);

        expect($requests)->toBe(2)
            ->and($ids)->toEqualCanonicalizing($trashed->pluck('id')->all());
    });

    it('does not page into another workspaces workflows', function () {
        $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Globex']);
        Workflow::factory()->forWorkspace($other)->count(3)->create();
        $own = Workflow::factory()->forWorkspace($this->workspace)->create();

        ['ids' => $ids] = ($this->walk)("{$this->base}/workflows", 2);

        expect($ids)->toBe([$own->id]);
    });
});

describe('triggers', function () {
    it('pages through every trigger once and keeps the target filter across pages', function () {
        $workflow = Workflow::factory()->forWorkspace($this->workspace)->published()->create();
        $triggers = Trigger::factory()->webhook()->forWorkflow($workflow)->count(5)->create(['created_at' => now()]);
        Trigger::factory()->webhook()->forWorkspace($this->workspace)->create();

        ['ids' => $ids, 'requests' => $requests] = ($this->walk)(
            "{$this->base}/triggers?target_type=workflow&target_id={$workflow->id}",
            2,
        );

        expect($requests)->toBe(3)
            ->and($ids)->toEqualCanonicalizing($triggers->pluck('id')->all());
    });

    it('rejects an out of range per_page', function () {
        $this->getJson("{$this->base}/triggers?per_page=0")->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson("{$this->base}/triggers?per_page=101")->assertUnprocessable()->assertJsonValidationErrors('per_page');
    });
});

describe('workflow versions', function () {
    it('pages the version history newest first', function () {
        $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
        foreach ([1, 2, 3] as $version) {
            WorkflowVersion::factory()->forWorkflow($workflow)->create(['version' => $version]);
        }

        $first = $this->getJson("{$this->base}/workflows/{$workflow->id}/versions?per_page=2")->assertOk();
        $second = $this->getJson("{$this->base}/workflows/{$workflow->id}/versions?per_page=2&cursor=".$first->json('meta.next_cursor'))->assertOk();

        expect(array_column($first->json('data'), 'version'))->toBe([3, 2])
            ->and(array_column($second->json('data'), 'version'))->toBe([1])
            ->and($second->json('meta.has_more'))->toBeFalse();
    });
});

describe('folders and page sections', function () {
    it('pages folders', function () {
        $folders = Folder::factory()->forWorkspace($this->workspace)->count(5)->create(['position' => 0]);

        ['ids' => $ids, 'requests' => $requests] = ($this->walk)("{$this->base}/folders", 2);

        expect($requests)->toBe(3)
            ->and($ids)->toEqualCanonicalizing($folders->pluck('id')->all());
    });
});
