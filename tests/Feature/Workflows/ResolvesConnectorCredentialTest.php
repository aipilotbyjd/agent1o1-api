<?php

use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\User;
use App\Nodes\Integrations\GitHub\GitHubGetRepoNode;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;

it('resolves an access token from a workspace-scoped connector credential', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $connector = Connector::factory()->create(['key' => 'github']);
    $credential = ConnectorCredential::factory()
        ->forWorkspace($workspace)
        ->forConnector($connector)
        ->create(['data' => ['access_token' => 'gh-from-credential']]);

    Http::fake(['api.github.com/repos/acme/widgets' => Http::response(['id' => 1])]);

    $run = Run::factory()->create(['workspace_id' => $workspace->id]);
    $node = new GitHubGetRepoNode;

    $node->execute($run, ['credential_id' => $credential->id, 'repo' => 'acme/widgets'], []);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer gh-from-credential'));
    expect($credential->fresh()->last_used_at)->not->toBeNull();
});

it('refuses a connector credential belonging to another workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherOwner = User::factory()->create();
    $otherWorkspace = app(WorkspaceService::class)->create($otherOwner, ['name' => 'Other']);
    $foreignCredential = ConnectorCredential::factory()->forWorkspace($otherWorkspace)->create();

    $run = Run::factory()->create(['workspace_id' => $workspace->id]);
    $node = new GitHubGetRepoNode;

    expect(fn () => $node->execute($run, ['credential_id' => $foreignCredential->id, 'repo' => 'acme/widgets'], []))
        ->toThrow(RuntimeException::class, 'not found in this workspace');
});

it('refuses an expired connector credential', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $connector = Connector::factory()->create(['key' => 'github']);
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)->expired()->create();

    $run = Run::factory()->create(['workspace_id' => $workspace->id]);
    $node = new GitHubGetRepoNode;

    expect(fn () => $node->execute($run, ['credential_id' => $credential->id, 'repo' => 'acme/widgets'], []))
        ->toThrow(RuntimeException::class, 'has expired');
});

it('falls back to the workspace\'s sole team credential when nothing is pinned', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $connector = Connector::factory()->create(['key' => 'github']);
    ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)
        ->create(['data' => ['access_token' => 'gh-team-default']]);

    Http::fake(['api.github.com/repos/acme/widgets' => Http::response(['id' => 1])]);
    $run = Run::factory()->create(['workspace_id' => $workspace->id]);

    (new GitHubGetRepoNode)->execute($run, ['repo' => 'acme/widgets'], []);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer gh-team-default'));
});

it('falls back to the credential explicitly marked default over an ambiguous set', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $connector = Connector::factory()->create(['key' => 'github']);
    ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)
        ->create(['data' => ['access_token' => 'gh-not-default']]);
    $default = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)
        ->create(['data' => ['access_token' => 'gh-default']]);
    $default->markAsDefault();

    Http::fake(['api.github.com/repos/acme/widgets' => Http::response(['id' => 1])]);
    $run = Run::factory()->create(['workspace_id' => $workspace->id]);

    (new GitHubGetRepoNode)->execute($run, ['repo' => 'acme/widgets'], []);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer gh-default'));
});

it('prefers the running user\'s personal default over the workspace\'s team credential', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $connector = Connector::factory()->create(['key' => 'github']);
    ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)
        ->create(['data' => ['access_token' => 'gh-team']]);
    ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)
        ->create(['created_by' => $owner->id, 'scope' => 'personal', 'data' => ['access_token' => 'gh-personal']]);

    Http::fake(['api.github.com/repos/acme/widgets' => Http::response(['id' => 1])]);
    $run = Run::factory()->create(['workspace_id' => $workspace->id, 'triggered_by' => $owner->id]);

    (new GitHubGetRepoNode)->execute($run, ['repo' => 'acme/widgets'], []);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer gh-personal'));
});

it('does not fall back when several team credentials exist and none is marked default', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $connector = Connector::factory()->create(['key' => 'github']);
    ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)->count(2)->create();

    $run = Run::factory()->create(['workspace_id' => $workspace->id]);

    expect(fn () => (new GitHubGetRepoNode)->execute($run, ['repo' => 'acme/widgets'], []))
        ->toThrow(RuntimeException::class, 'access_token or credential_id is required');
});

it('refuses a pinned credential that belongs to a different connector', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    Connector::factory()->create(['key' => 'github']);
    $slack = Connector::factory()->create(['key' => 'slack']);
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($slack)->create();

    $run = Run::factory()->create(['workspace_id' => $workspace->id]);

    expect(fn () => (new GitHubGetRepoNode)->execute($run, ['credential_id' => $credential->id, 'repo' => 'acme/widgets'], []))
        ->toThrow(RuntimeException::class, 'not found in this workspace');
});

it('refuses another member\'s personal credential even when pinned by id', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $connector = Connector::factory()->create(['key' => 'github']);
    $teammate = User::factory()->create();
    $personal = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)
        ->create(['scope' => 'personal', 'created_by' => $teammate->id, 'data' => ['access_token' => 'gh-private']]);

    Http::fake(['api.github.com/repos/acme/widgets' => Http::response(['id' => 1])]);

    $ownersRun = Run::factory()->create(['workspace_id' => $workspace->id, 'triggered_by' => $owner->id]);
    expect(fn () => (new GitHubGetRepoNode)->execute($ownersRun, ['credential_id' => $personal->id, 'repo' => 'acme/widgets'], []))
        ->toThrow(RuntimeException::class, 'not found in this workspace');

    $teammatesRun = Run::factory()->create(['workspace_id' => $workspace->id, 'triggered_by' => $teammate->id]);
    (new GitHubGetRepoNode)->execute($teammatesRun, ['credential_id' => $personal->id, 'repo' => 'acme/widgets'], []);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer gh-private'));
});
