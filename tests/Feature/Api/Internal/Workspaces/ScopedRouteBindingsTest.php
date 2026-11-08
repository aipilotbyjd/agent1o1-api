<?php

use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Reflector;
use Laravel\Passport\Passport;

/*
 * Every route under /workspaces/{workspace} finds its models through the
 * workspace (`->scopeBindings()`), so a model from another workspace is a
 * 404 before a controller runs, whether or not that controller remembered
 * `ensureBelongsToWorkspace()`.
 *
 * Laravel finds `{child}` through the relation named after the parameter on
 * the previous parameter's model, and a parameter with no such relation is a
 * 500. The sweep below proves every route resolves through a real relation
 * of the right model.
 */

/**
 * The workspace routes that are not resolved through the workspace, and why.
 * A new entry needs a reason a reviewer would accept.
 *
 * @return array<string, string> URI prefix => reason
 */
function unscopedWorkspaceRoutes(): array
{
    return [
        'api/v1/workspaces/{workspace}/assistant' => 'Resources belong to the member\'s own assistant, checked by ResolvesOwnAssistant.',
        'api/v1/workspaces/{workspace}/workflow-templates' => 'Templates can be platform-wide; the controller checks they are this workspace\'s or public.',
        'api/v1/workspaces/{workspace}/agent-templates' => 'Same: platform-wide templates are readable from every workspace.',
        'api/v1/workspaces/{workspace}/page-templates' => 'Same: platform-wide templates are readable from every workspace.',
        'api/v1/workspaces/{workspace}/template-collections' => 'Same: platform-wide collections are readable from every workspace.',
        'api/v1/workspaces/{workspace}/artifacts/{artifact}/shares/{user}' => '{user} is any user; removeShare() checks the artifact\'s workspace itself.',
    ];
}

/**
 * @return list<LaravelRoute>
 */
function workspaceScopedRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route) => in_array('workspace.context', $route->gatherMiddleware(), true))
        ->filter(fn (LaravelRoute $route) => str_contains($route->uri(), '{workspace}'))
        ->values()
        ->all();
}

function isExemptWorkspaceRoute(LaravelRoute $route): bool
{
    foreach (array_keys(unscopedWorkspaceRoutes()) as $prefix) {
        if (str_starts_with($route->uri(), $prefix)) {
            return true;
        }
    }

    return false;
}

it('finds every workspace route to check', function () {
    expect(count(workspaceScopedRoutes()))->toBeGreaterThan(300);
});

it('scopes the bindings of every workspace route, except the documented ones', function () {
    $unscoped = collect(workspaceScopedRoutes())
        ->reject(fn (LaravelRoute $route) => $route->enforcesScopedBindings() || isExemptWorkspaceRoute($route))
        ->map(fn (LaravelRoute $route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($unscoped)->toBe([], 'Add ->scopeBindings() to the route group, or document why it cannot be scoped.');
});

it('only exempts routes that are really unscoped', function () {
    foreach (workspaceScopedRoutes() as $route) {
        if (isExemptWorkspaceRoute($route)) {
            expect($route->enforcesScopedBindings())->toBeFalse("{$route->uri()} is listed as exempt but is still scoped.");
        }
    }
});

it('resolves every scoped parameter through a relation of the right model', function () {
    $broken = [];
    $checked = 0;

    foreach (workspaceScopedRoutes() as $route) {
        if (! $route->enforcesScopedBindings()) {
            continue;
        }

        $uriParameters = $route->parameterNames();
        $modelsByParameter = [];
        $resolved = [];

        foreach ($route->signatureParameters(['subClass' => UrlRoutable::class]) as $parameter) {
            $name = in_array($parameter->getName(), $uriParameters, true)
                ? $parameter->getName()
                : str($parameter->getName())->snake()->toString();

            if (! in_array($name, $uriParameters, true)) {
                continue;
            }

            $modelsByParameter[$name] = Reflector::getParameterClassName($parameter);
            $position = array_search($name, $uriParameters, true);
            $parent = $position > 0 ? $uriParameters[$position - 1] : null;

            if ($parent !== null && isset($resolved[$parent])) {
                $checked++;
                $parentClass = $modelsByParameter[$parent];
                $childClass = $modelsByParameter[$name];

                /** @var Model $parentModel */
                $parentModel = new $parentClass;
                $relationName = (new ReflectionMethod($parentModel, 'childRouteBindingRelationshipName'))->invoke($parentModel, $name);

                if (! method_exists($parentModel, $relationName)) {
                    $broken[] = "{$route->uri()}: {$parentClass} has no {$relationName}() for {{$name}}";
                } else {
                    $relation = $parentModel->{$relationName}();

                    if (! $relation instanceof Relation || ! $relation->getRelated() instanceof $childClass) {
                        $broken[] = "{$route->uri()}: {$parentClass}::{$relationName}() does not return {$childClass}";
                    }
                }
            }

            $resolved[$name] = true;
        }
    }

    expect($checked)->toBeGreaterThan(200)
        ->and(array_values(array_unique($broken)))->toBe([]);
});

it('binds every parent before its child, so scoping is never silently skipped', function () {
    $misordered = [];

    foreach (workspaceScopedRoutes() as $route) {
        if (! $route->enforcesScopedBindings()) {
            continue;
        }

        $uriParameters = $route->parameterNames();
        $seen = [];

        foreach ($route->signatureParameters(['subClass' => UrlRoutable::class]) as $parameter) {
            $position = array_search($parameter->getName(), $uriParameters, true);

            if ($position > 0 && ! isset($seen[$uriParameters[$position - 1]])) {
                $misordered[] = "{$route->uri()}: {{$parameter->getName()}} is bound before its parent {{$uriParameters[$position - 1]}}";
            }

            $seen[$parameter->getName()] = true;
        }
    }

    expect($misordered)->toBe([]);
});

describe('the mechanism, without any controller check', function () {
    beforeEach(function () {
        $this->owner = User::factory()->create();
        $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
        $this->other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Globex']);

        Passport::actingAs($this->owner);

        Route::middleware(['auth:api', 'workspace.context'])
            ->prefix('api/v1/workspaces/{workspace}/__probe')
            ->scopeBindings()
            ->group(function () {
                Route::get('workflows/{workflow}', fn (Workspace $workspace, Workflow $workflow) => response()->json(['id' => $workflow->id]));
                Route::get('agents/{agent}', fn (Workspace $workspace, Agent $agent) => response()->json(['id' => $agent->id]));
                Route::get('agents/{agent}/workflows/{workflow}', fn (Workspace $workspace, Agent $agent, Workflow $workflow) => response()->json(['id' => $workflow->id]));
                Route::get('members/{member}', fn (Workspace $workspace, User $member) => response()->json(['id' => $member->id]));
            });

        $this->probe = "/api/v1/workspaces/{$this->workspace->id}/__probe";
    });

    it('404s a model that belongs to another workspace', function () {
        $own = Workflow::factory()->forWorkspace($this->workspace)->create();
        $foreign = Workflow::factory()->forWorkspace($this->other)->create();

        $this->getJson("{$this->probe}/workflows/{$own->id}")->assertOk()->assertJsonPath('id', $own->id);
        $this->getJson("{$this->probe}/workflows/{$foreign->id}")->assertNotFound();
    });

    it('scopes a child under another model to the workspace too', function () {
        $agent = Agent::factory()->forWorkspace($this->workspace)->create();
        $ownWorkflow = Workflow::factory()->forWorkspace($this->workspace)->create();
        $foreignWorkflow = Workflow::factory()->forWorkspace($this->other)->create();
        $foreignAgent = Agent::factory()->forWorkspace($this->other)->create();

        // Not attached to the agent yet: attaching needs to find it.
        $this->getJson("{$this->probe}/agents/{$agent->id}/workflows/{$ownWorkflow->id}")->assertOk();
        $this->getJson("{$this->probe}/agents/{$agent->id}/workflows/{$foreignWorkflow->id}")->assertNotFound();
        $this->getJson("{$this->probe}/agents/{$foreignAgent->id}")->assertNotFound();
    });

    it('resolves a member through the workspace membership', function () {
        $member = User::factory()->create();
        $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
        $outsider = User::factory()->create();

        $this->getJson("{$this->probe}/members/{$member->id}")->assertOk();
        $this->getJson("{$this->probe}/members/{$outsider->id}")->assertNotFound();
    });
});
