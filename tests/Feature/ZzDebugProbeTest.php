<?php

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;

it('debug: probe route', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    Passport::actingAs($owner);

    Route::middleware(['auth:api', 'workspace.context'])
        ->prefix('api/v1/workspaces/{workspace}/__probe')
        ->scopeBindings()
        ->group(function () {
            Route::get('workflows/{workflow}', fn (Workspace $workspace, Workflow $workflow) => response()->json(['id' => $workflow->id]));
        });

    $own = Workflow::factory()->forWorkspace($workspace)->create();
    $url = "/api/v1/workspaces/{$workspace->id}/__probe/workflows/{$own->id}";
    $response = $this->getJson($url);

    $own->refresh();
    $info = [
        'status' => $response->getStatusCode(),
        'body' => $response->getContent(),
        'collection' => get_class(Route::getRoutes()),
        'workflow_trashed' => method_exists($own, 'trashed') ? $own->trashed() : null,
        'workspace_workflows' => $workspace->workflows()->count(),
        'workspace_workflows_withTrashed' => method_exists($workspace->workflows(), 'withTrashed') ? $workspace->workflows()->withTrashed()->count() : 'n/a',
        'own_workspace_id' => $own->workspace_id,
        'workspace_id' => $workspace->id,
    ];

    expect(json_encode($info))->toBe('DEBUGPROBE');
});
