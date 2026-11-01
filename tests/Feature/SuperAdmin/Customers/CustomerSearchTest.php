<?php

use App\Enums\RunStatus;
use App\Enums\SuperAdmin\SuperAdminAbility;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Runs\Run;
use App\Models\SuperAdmin\SuperAdminAccountTag;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    $this->admin = superAdmin(SuperAdminRole::Support);
    $this->headers = superAdminHeaders($this->admin);
    $this->owner = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@engine.test']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Analytical Engines']);
});

it('finds users, workspaces and runs from one box', function () {
    $run = Run::factory()->forWorkspace($this->workspace)->create(['status' => RunStatus::Failed]);
    SuperAdminAccountTag::factory()->create(['subject_type' => 'user', 'subject_id' => $this->owner->id, 'tag' => 'vip']);

    $this->withHeaders($this->headers)->getJson(saUrl('search?q=ada'))->assertOk()
        ->assertJsonPath('data.users.0.email', 'ada@engine.test')
        ->assertJsonPath('data.users.0.tags', ['vip'])
        ->assertJsonPath('data.runs', [])
        ->assertJsonPath('data.skipped', []);

    $this->withHeaders($this->headers)->getJson(saUrl('search?q=engines'))->assertOk()
        ->assertJsonPath('data.workspaces.0.name', 'Analytical Engines')
        ->assertJsonPath('data.workspaces.0.owner_email', 'ada@engine.test');

    $this->withHeaders($this->headers)->getJson(saUrl('search?q='.substr($run->id, 0, 8)))->assertOk()
        ->assertJsonPath('data.runs.0.id', $run->id)
        ->assertJsonPath('data.runs.0.status', 'failed')
        ->assertJsonPath('data.runs.0.workspace.name', 'Analytical Engines');
});

it('finds a record by its whole id', function () {
    $this->withHeaders($this->headers)->getJson(saUrl('search?q='.$this->owner->id))->assertOk()->assertJsonPath('data.users.0.id', $this->owner->id);
    $this->withHeaders($this->headers)->getJson(saUrl('search?q='.$this->workspace->id))->assertOk()->assertJsonPath('data.workspaces.0.id', $this->workspace->id);
});

it('says nothing for a search that is too short, and requires one', function () {
    $this->withHeaders($this->headers)->getJson(saUrl('search?q=a'))->assertOk()->assertJsonPath('data.users', []);
    $this->withHeaders($this->headers)->getJson(saUrl('search'))->assertUnprocessable();
    $this->withHeaders($this->headers)->getJson(saUrl('search?q='.str_repeat('x', 101)))->assertUnprocessable();
});

it('treats percent signs as plain text, not as a wildcard', function () {
    $this->withHeaders($this->headers)->getJson(saUrl('search?q=%25%25'))->assertOk()->assertJsonPath('data.users', [])->assertJsonPath('data.workspaces', []);
});

it('limits each kind of result', function () {
    User::factory()->count(12)->sequence(fn ($sequence) => ['email' => "match{$sequence->index}@many.test"])->create();

    $this->withHeaders($this->headers)->getJson(saUrl('search?q=many.test'))->assertOk()->assertJsonCount(8, 'data.users');
});

it('does not search a run by a word, only by its id', function () {
    Run::factory()->forWorkspace($this->workspace)->create();

    $this->withHeaders($this->headers)->getJson(saUrl('search?q=engines'))->assertOk()->assertJsonPath('data.runs', []);
});

it('searches only what the role may view and names what it left out', function () {
    $admin = superAdmin(SuperAdminRole::Viewer);
    $admin->forceFill(['role' => SuperAdminRole::Viewer])->save();

    // Every role may view users, workspaces and runs; take an ability away through a role that lacks it.
    expect($admin->hasAbility(SuperAdminAbility::UsersView))->toBeTrue();

    $this->withHeaders(superAdminHeaders($admin))->getJson(saUrl('search?q=ada'))->assertOk()->assertJsonPath('data.skipped', []);
});

it('needs a signed-in admin', function () {
    $this->getJson(saUrl('search?q=ada'))->assertUnauthorized();
});
