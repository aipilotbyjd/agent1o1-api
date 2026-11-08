<?php

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;

function zzProbeRoutes(string $name): void
{
    Route::middleware(['auth:api', 'workspace.context'])
        ->prefix("api/v1/workspaces/{workspace}/{$name}")
        ->scopeBindings()
        ->group(function () {
            Route::get('workflows/{workflow}', fn (Workspace $workspace, Workflow $workflow) => response()->json(['id' => $workflow->id]));
        });
}

it('debug: probe route variants', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    Passport::actingAs($owner);
    $own = Workflow::factory()->forWorkspace($workspace)->create();

    $info = [];

    // A: register first, then request (what the failing tests do).
    $before = count(Route::getRoutes()->getRoutes());
    zzProbeRoutes('__a');
    $info['routes_before_a'] = $before;
    $info['routes_after_register_a'] = count(Route::getRoutes()->getRoutes());
    $info['a_status'] = $this->getJson("/api/v1/workspaces/{$workspace->id}/__a/workflows/{$own->id}")->getStatusCode();
    $info['routes_after_request_a'] = count(Route::getRoutes()->getRoutes());
    $info['a_still_registered'] = collect(Route::getRoutes()->getRoutes())->contains(fn ($r) => str_contains($r->uri(), '__a'));

    // B: request something real first, then register, then request.
    $this->getJson('/up');
    zzProbeRoutes('__b');
    $info['b_status'] = $this->getJson("/api/v1/workspaces/{$workspace->id}/__b/workflows/{$own->id}")->getStatusCode();

    // C: route list as seen by the router through the app container.
    $info['same_collection'] = Route::getRoutes() === app('router')->getRoutes();
    $info['laravel'] = app()->version();
    $info['php'] = PHP_VERSION;

    expect(json_encode($info))->toBe('DEBUGPROBE');
});
