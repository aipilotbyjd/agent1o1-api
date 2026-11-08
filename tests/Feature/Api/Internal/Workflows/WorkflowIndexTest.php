<?php

use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Tag;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/*
 * The workflows page is filtered, sorted and paged on the server.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->base = "/api/v1/workspaces/{$this->workspace->id}/workflows";

    Passport::actingAs($this->owner);

    $this->names = fn (string $query = '') => array_column($this->getJson($this->base.$query)->assertOk()->json('data'), 'name');
});

it('pages by number and reports how many match', function () {
    Workflow::factory()->forWorkspace($this->workspace)->count(7)->create();

    $first = $this->getJson("{$this->base}?per_page=3")->assertOk();
    $last = $this->getJson("{$this->base}?per_page=3&page=3")->assertOk();

    expect($first->json('data'))->toHaveCount(3)
        ->and($first->json('meta'))->toBe(['current_page' => 1, 'last_page' => 3, 'per_page' => 3, 'total' => 7])
        ->and($last->json('data'))->toHaveCount(1);

    $ids = collect([1, 2, 3])->flatMap(fn (int $page) => array_column($this->getJson("{$this->base}?per_page=3&page={$page}")->json('data'), 'id'));
    expect($ids->unique())->toHaveCount(7);
});

it('caps per_page and rejects an unknown sort or status', function () {
    $this->getJson("{$this->base}?per_page=101")->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson("{$this->base}?sort=bogus")->assertUnprocessable()->assertJsonValidationErrors('sort');
    $this->getJson("{$this->base}?status=bogus")->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('searches names, descriptions, tags and node types', function () {
    $named = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'Invoice sync', 'description' => 'x']);
    $described = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'Alpha', 'description' => 'Sends invoices']);
    $tagged = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'Beta', 'description' => 'x']);
    $tagged->tags()->attach(Tag::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Invoicing']));
    $withNode = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'Gamma', 'description' => 'x']);
    $withNode->nodes()->create(['key' => 'a', 'type' => 'invoiceninja.create', 'config' => []]);
    Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'Unrelated', 'description' => 'x']);

    expect(($this->names)('?search=invoic'))->toEqualCanonicalizing([$named->name, $described->name, $tagged->name, $withNode->name]);
    expect(($this->names)('?search=nothing-matches'))->toBe([]);
});

it('filters by status, favorites and tag', function () {
    $draft = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'Draft']);
    $published = Workflow::factory()->forWorkspace($this->workspace)->published()->create(['name' => 'Live']);
    $tag = Tag::factory()->create(['workspace_id' => $this->workspace->id]);
    $draft->tags()->attach($tag);
    $published->favoritedByUsers()->attach($this->owner);

    expect(($this->names)('?status=published'))->toBe(['Live'])
        ->and(($this->names)('?status=draft'))->toBe(['Draft'])
        ->and(($this->names)('?favorites=1'))->toBe(['Live'])
        ->and(($this->names)("?tag_id={$tag->id}"))->toBe(['Draft']);
});

it('sorts by name, node count and last run, newest or biggest first', function () {
    $b = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'B']);
    $a = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'A']);
    $c = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'C']);
    $b->nodes()->create(['key' => 'x', 'type' => 'transform', 'config' => []]);
    $b->nodes()->create(['key' => 'y', 'type' => 'transform', 'config' => []]);
    $c->nodes()->create(['key' => 'x', 'type' => 'transform', 'config' => []]);
    Run::factory()->forWorkflow($a)->completed()->create(['finished_at' => now()->subDay()]);
    Run::factory()->forWorkflow($c)->completed()->create(['finished_at' => now()]);

    expect(($this->names)('?sort=name'))->toBe(['A', 'B', 'C'])
        ->and(($this->names)('?sort=nodes'))->toBe(['B', 'C', 'A'])
        ->and(($this->names)('?sort=last_run'))->toBe(['C', 'A', 'B']);
});

it('keeps filters across pages', function () {
    Workflow::factory()->forWorkspace($this->workspace)->published()->count(5)->create();
    Workflow::factory()->forWorkspace($this->workspace)->count(4)->create();

    $page = $this->getJson("{$this->base}?status=published&per_page=2&page=3")->assertOk();

    expect($page->json('data'))->toHaveCount(1)->and($page->json('meta.total'))->toBe(5);
});

it('summarises the workspace without loading the list', function () {
    Workflow::factory()->forWorkspace($this->workspace)->count(2)->create();
    $live = Workflow::factory()->forWorkspace($this->workspace)->published()->create();
    Workflow::factory()->forWorkspace($this->workspace)->create(['is_internal' => true]);
    $live->favoritedByUsers()->attach($this->owner);

    $this->getJson("{$this->base}/summary")
        ->assertOk()
        ->assertJsonPath('data', ['total' => 3, 'published' => 1, 'favorites' => 1]);
});

it('runs a fixed number of queries however many workflows exist', function () {
    Workflow::factory()->forWorkspace($this->workspace)->count(3)->create();
    $this->getJson("{$this->base}?per_page=2")->assertOk();

    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson("{$this->base}?per_page=2")->assertOk();

        return count(DB::getQueryLog());
    };

    $before = $count();
    Workflow::factory()->forWorkspace($this->workspace)->count(20)->create();

    expect($count())->toBe($before);
});
