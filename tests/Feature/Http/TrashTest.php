<?php

use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Billing\Plan;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function trashWorkspace(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    Passport::actingAs($owner);

    return [$workspace, $owner];
}

dataset('trashable', [
    'workflows' => ['workflows', 'workflow', Workflow::class],
    'agents' => ['agents', 'agent', Agent::class],
]);

it('moves an item to the trash and lists it there', function (string $path, string $key, string $model) {
    [$workspace] = trashWorkspace();
    $item = $model::factory()->forWorkspace($workspace)->create();
    $kept = $model::factory()->forWorkspace($workspace)->create();

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/{$path}/{$item->id}")->assertNoContent();

    $this->getJson("/api/v1/workspaces/{$workspace->id}/{$path}")
        ->assertOk()
        ->assertJsonCount(1, "data.{$path}")
        ->assertJsonPath("data.{$path}.0.id", $kept->id);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/{$path}/trash")
        ->assertOk()
        ->assertJsonCount(1, "data.{$path}")
        ->assertJsonPath("data.{$path}.0.id", $item->id)
        ->assertJsonPath("data.{$path}.0.deleted_at", fn ($value) => $value !== null);
})->with('trashable');

it('restores a trashed item', function (string $path, string $key, string $model) {
    [$workspace] = trashWorkspace();
    $item = $model::factory()->forWorkspace($workspace)->create();
    $item->delete();

    $this->postJson("/api/v1/workspaces/{$workspace->id}/{$path}/{$item->id}/restore")
        ->assertOk()
        ->assertJsonPath("data.{$key}.id", $item->id)
        ->assertJsonPath("data.{$key}.deleted_at", null);

    expect($item->fresh()->trashed())->toBeFalse();
    $this->getJson("/api/v1/workspaces/{$workspace->id}/{$path}/trash")->assertJsonCount(0, "data.{$path}");
})->with('trashable');

it('permanently deletes a trashed item', function (string $path, string $key, string $model) {
    [$workspace] = trashWorkspace();
    $item = $model::factory()->forWorkspace($workspace)->create();
    $item->delete();

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/{$path}/{$item->id}/force")->assertNoContent();

    expect($model::withTrashed()->find($item->id))->toBeNull();
})->with('trashable');

it('only restores or permanently deletes items that are in the trash', function (string $path, string $key, string $model) {
    [$workspace] = trashWorkspace();
    $item = $model::factory()->forWorkspace($workspace)->create();

    $this->postJson("/api/v1/workspaces/{$workspace->id}/{$path}/{$item->id}/restore")->assertNotFound();
    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/{$path}/{$item->id}/force")->assertNotFound();

    expect($item->fresh())->not->toBeNull();
})->with('trashable');

it('404s on another workspace\'s trashed item', function (string $path, string $key, string $model) {
    [$workspace] = trashWorkspace();
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    $item = $model::factory()->forWorkspace($other)->create();
    $item->delete();

    $this->postJson("/api/v1/workspaces/{$workspace->id}/{$path}/{$item->id}/restore")->assertNotFound();
    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/{$path}/{$item->id}/force")->assertNotFound();
    $this->getJson("/api/v1/workspaces/{$workspace->id}/{$path}/trash")->assertJsonCount(0, "data.{$path}");
})->with('trashable');

it('lets a viewer see the trash but not restore or purge it', function (string $path, string $key, string $model) {
    [$workspace] = trashWorkspace();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    $item = $model::factory()->forWorkspace($workspace)->create();
    $item->delete();

    Passport::actingAs($viewer);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/{$path}/trash")->assertOk();
    $this->postJson("/api/v1/workspaces/{$workspace->id}/{$path}/{$item->id}/restore")->assertForbidden();
    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/{$path}/{$item->id}/force")->assertForbidden();
})->with('trashable');

it('refuses to restore past the plan limit', function (string $path, string $key, string $model) {
    Plan::factory()->create(['slug' => 'free', 'limits' => [$path => 1]]);
    config(['billing.default_plan' => 'free']);
    [$workspace] = trashWorkspace();

    $trashed = $model::factory()->forWorkspace($workspace)->create();
    $trashed->delete();
    $model::factory()->forWorkspace($workspace)->create();

    $this->postJson("/api/v1/workspaces/{$workspace->id}/{$path}/{$trashed->id}/restore")->assertStatus(402);

    expect($trashed->fresh()->trashed())->toBeTrue();
})->with('trashable');

it('keeps internal loop workflows out of the trash and purges them with their parent', function () {
    [$workspace] = trashWorkspace();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $child = Workflow::factory()->forWorkspace($workspace)->create([
        'is_internal' => true,
        'slug' => "loop-{$workflow->id}-step",
    ]);
    $unrelated = Workflow::factory()->forWorkspace($workspace)->create(['is_internal' => true, 'slug' => 'loop-someone-else-step']);
    $workflow->delete();
    $child->delete();

    $this->getJson("/api/v1/workspaces/{$workspace->id}/workflows/trash")
        ->assertJsonCount(1, 'data.workflows')
        ->assertJsonPath('data.workflows.0.id', $workflow->id);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/force")->assertNoContent();

    expect(Workflow::withTrashed()->find($child->id))->toBeNull()
        ->and(Workflow::find($unrelated->id))->not->toBeNull();
});
