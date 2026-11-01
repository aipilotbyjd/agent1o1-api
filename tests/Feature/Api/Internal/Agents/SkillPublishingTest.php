<?php

use App\Enums\Agents\SkillSourceStatus;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Agents\Skill;
use App\Models\Agents\SkillSource;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Services\Agents\Skills\SkillSync;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as GitHubRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

/** @param array<string, string> $files */
function publishingTarball(array $files): string
{
    $path = sys_get_temp_dir().'/publish-'.uniqid().'.tar';
    $tar = new PharData($path);
    foreach ($files as $name => $contents) {
        $tar->addFromString('owner-repo-sha/'.$name, $contents);
    }
    $tar->compress(Phar::GZ);
    $body = file_get_contents($path.'.gz');
    unset($tar);
    unlink($path);
    unlink($path.'.gz');

    return $body;
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Publishing']);
    $connector = Connector::query()->where('key', 'github')->first() ?? Connector::factory()->create(['key' => 'github']);
    $this->credential = ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($connector)->create([
        'scope' => ConnectorCredentialScope::Personal, 'created_by' => $this->owner->id, 'data' => ['access_token' => 'publish-token'],
    ]);
    $this->url = "/api/v1/workspaces/{$this->workspace->id}";
    $this->files = ['s/SKILL.md' => 'Original.'];
    $this->forkReady = true;
    $this->canPush = true;
    $this->protectedBranch = false;
    $this->head = 'sha1';
    $this->upstreamHead = 'upstream-sha';
    $this->mergeStatus = 201;
    $this->readStatus = 200;
    $this->parentRepo = 'acme/skills';
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(function (GitHubRequest $request) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if ($path === '/user') {
            return Http::response(['login' => 'me']);
        }
        if ($path === '/repos/acme/skills/forks' && $request->method() === 'POST') {
            return Http::response(['full_name' => 'me/skills'], 202);
        }
        if (str_ends_with($path, '/branches/main')) {
            if ($this->readStatus !== 200) {
                return Http::response(['message' => 'Account access rejected'], $this->readStatus);
            }
            if (str_starts_with($path, '/repos/me/') && ! $this->forkReady) {
                return Http::response([], 404);
            }

            return Http::response(['commit' => ['sha' => str_starts_with($path, '/repos/acme/') ? $this->upstreamHead : $this->head], 'protected' => $this->protectedBranch]);
        }
        if (str_contains($path, '/tarball/')) {
            return Http::response(publishingTarball($this->files));
        }
        if (str_contains($path, '/git/commits/') && $request->method() === 'GET') {
            return Http::response(['tree' => ['sha' => 'tree']]);
        }
        if (str_ends_with($path, '/git/trees') || str_ends_with($path, '/git/commits') || str_contains($path, '/git/refs/')) {
            return Http::response(['sha' => 'published-sha']);
        }
        if (str_contains($path, '/compare/')) {
            return Http::response(['ahead_by' => 1, 'files' => [['filename' => 's/SKILL.md', 'status' => 'modified', 'patch' => '+Update']]]);
        }
        if (str_ends_with($path, '/merges')) {
            return Http::response(['sha' => 'merged-sha', 'message' => 'Merge conflict'], $this->mergeStatus);
        }
        if (in_array($path, ['/repos/acme/skills', '/repos/me/skills'], true)) {
            return Http::response(['default_branch' => 'main', 'private' => false, 'permissions' => ['push' => $path === '/repos/me/skills' && $this->canPush], 'parent' => $path === '/repos/me/skills' ? ['full_name' => $this->parentRepo] : null]);
        }
        throw new RuntimeException('Unexpected GitHub request: '.$request->method().' '.$path);
    });
    Passport::actingAs($this->owner);
});

function importedPublishingSource(object $test, bool $writable = false): SkillSource
{
    $source = SkillSource::factory()->create([
        'workspace_id' => $test->workspace->id, 'created_by' => $test->owner->id,
        'repo' => $writable ? 'me/skills' : 'acme/skills', 'branch' => 'main',
        'connector_credential_id' => $test->credential->id, 'two_way' => $writable,
        'upstream_repo' => $writable ? 'acme/skills' : null, 'upstream_branch' => $writable ? 'main' : null,
    ]);
    app(SkillSync::class)->sync($source);

    return $source->refresh();
}

it('imports another public repository without checking publishing permissions', function () {
    $this->postJson($this->url.'/skill-sources', ['repo' => 'acme/skills', 'branch' => 'main'])->assertCreated();
    expect(SkillSource::query()->sole()->two_way)->toBeFalse();
    Http::assertNotSent(fn (GitHubRequest $request): bool => $request->url() === 'https://api.github.com/repos/acme/skills');
});

it('creates an independent editable copy with references scripts and original attribution', function () {
    $this->files += ['s/ref.md' => 'Reference', 's/run.py' => 'print(1)'];
    $source = importedPublishingSource($this);
    $original = $source->skills()->sole();
    $agent = Agent::factory()->create(['workspace_id' => $this->workspace->id]);
    $agent->skills()->attach($original);
    $response = $this->postJson("{$this->url}/skills/{$original->id}/copy")->assertCreated()->assertJsonPath('data.skill.skill_source_id', null);
    $copy = Skill::query()->findOrFail($response->json('data.skill.id'));

    expect($copy)->instructions->toBe('Original.')->version->toBe(1)->origin_url->toBe('https://github.com/acme/skills/tree/main/s')
        ->and($copy->references()->sole()->content)->toBe('Reference')
        ->and($copy->scripts()->sole()->code)->toBe('print(1)')
        ->and($agent->skills()->pluck('skills.id')->all())->toBe([$original->id]);
    $this->patchJson("{$this->url}/skills/{$copy->id}", ['instructions' => 'My version.'])->assertOk();
    expect($original->refresh()->instructions)->toBe('Original.');
});

it('only enables publishing on an account and branch with write access', function (bool $canPush, bool $protected) {
    $this->canPush = $canPush;
    $this->protectedBranch = $protected;
    $this->postJson($this->url.'/skill-sources/access', ['repo' => 'me/skills', 'branch' => 'main', 'credential_id' => $this->credential->id])->assertOk()->assertJsonPath('data.access.can_push', false);
    $source = importedPublishingSource($this);
    $this->patchJson("{$this->url}/skill-sources/{$source->id}", ['two_way' => true])->assertUnprocessable();
    expect($source->refresh()->two_way)->toBeFalse();
})->with([[false, false], [true, true]]);

it('publishes a local skill to its chosen folder and optionally keeps it synced', function (bool $keepSynced) {
    $skill = Skill::factory()->create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id, 'instructions' => 'My skill.']);
    $response = $this->postJson("{$this->url}/skills/{$skill->id}/publish", ['repo' => 'me/skills', 'branch' => 'main', 'credential_id' => $this->credential->id, 'path' => 'custom/new-skill', 'keep_synced' => $keepSynced])->assertCreated();

    expect($skill->refresh()->isSynced())->toBe($keepSynced);
    Http::assertSent(fn (GitHubRequest $request): bool => str_ends_with($request->url(), '/git/trees') && collect($request['tree'])->contains(fn (array $file): bool => $file['path'] === 'custom/new-skill/SKILL.md' && str_contains($file['content'], 'My skill.')));
    if ($keepSynced) {
        expect(SkillSource::query()->sole())->repo->toBe('me/skills')->path->toBe('custom/new-skill')->status->toBe(SkillSourceStatus::Ready);
        $response->assertJsonPath('data.source.repository_private', false);
    } else {
        expect(SkillSource::query()->count())->toBe(0);
    }
})->with([true, false]);

it('rejects publishing into another repository or an existing skill folder', function (string $repo, string $path) {
    $skill = Skill::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->postJson("{$this->url}/skills/{$skill->id}/publish", ['repo' => $repo, 'branch' => 'main', 'credential_id' => $this->credential->id, 'path' => $path, 'keep_synced' => true])->assertUnprocessable();
    expect($skill->refresh()->isSynced())->toBeFalse()->and(SkillSource::query()->count())->toBe(0);
    Http::assertNotSent(fn (GitHubRequest $request): bool => $request->method() !== 'GET');
})->with([['acme/skills', 'custom'], ['me/skills', 's'], ['me/skills', 's/nested'], ['me/skills', '../escape']]);

it('waits for a fork before switching and preserves skill identities and attachments', function () {
    $source = importedPublishingSource($this);
    $skill = $source->skills()->sole();
    $agent = Agent::factory()->create(['workspace_id' => $this->workspace->id]);
    $agent->skills()->attach($skill);
    $this->forkReady = false;
    $this->postJson("{$this->url}/skill-sources/{$source->id}/fork", ['credential_id' => $this->credential->id])->assertOk();
    $this->postJson("{$this->url}/skill-sources/{$source->id}/fork/complete")->assertOk()->assertJsonPath('data.source.repo', 'acme/skills')->assertJsonPath('data.source.status', 'forking');
    $this->forkReady = true;
    $this->postJson("{$this->url}/skill-sources/{$source->id}/fork/complete")->assertOk()->assertJsonPath('data.source.repo', 'me/skills')->assertJsonPath('data.source.two_way', true);
    app(SkillSync::class)->sync($source->refresh());

    expect($source->refresh())->upstream_repo->toBe('acme/skills')->status->toBe(SkillSourceStatus::Ready)
        ->and($source->skills()->sole()->origin_url)->toBe('https://github.com/acme/skills/tree/main/s')
        ->and($source->skills()->sole()->id)->toBe($skill->id)
        ->and($agent->skills()->sole()->id)->toBe($skill->id);
});

it('keeps the original connection when the chosen existing repository is not a direct fork', function () {
    $source = importedPublishingSource($this);
    $this->parentRepo = 'someone/else';
    $this->postJson("{$this->url}/skill-sources/{$source->id}/fork", ['credential_id' => $this->credential->id, 'fork_repo' => 'me/skills'])->assertOk();
    $this->postJson("{$this->url}/skill-sources/{$source->id}/fork/complete")->assertUnprocessable();
    expect($source->refresh())->repo->toBe('acme/skills')->two_way->toBeFalse();
    $this->deleteJson("{$this->url}/skill-sources/{$source->id}/fork")->assertOk();
    expect($source->refresh())->fork_request->toBeNull()->status->toBe(SkillSourceStatus::Ready);
});

it('checks original updates and merges only the reviewed upstream commit', function () {
    $source = importedPublishingSource($this, writable: true);
    $this->getJson("{$this->url}/skill-sources/{$source->id}/upstream")->assertOk()->assertJsonPath('data.upstream.commits_ahead', 1)->assertJsonPath('data.upstream.files.0.path', 's/SKILL.md');
    Http::assertNotSent(fn (GitHubRequest $request): bool => $request->method() !== 'GET');
    $this->postJson("{$this->url}/skill-sources/{$source->id}/upstream", ['fork_sha' => 'sha1', 'upstream_sha' => 'upstream-sha'])->assertOk();
    Http::assertSent(fn (GitHubRequest $request): bool => str_ends_with($request->url(), '/merges') && $request['head'] === 'upstream-sha' && $request['base'] === 'main');
});

it('rejects outdated upstream reviews and pending local changes', function (bool $localEdit) {
    $source = importedPublishingSource($this, writable: true);
    if ($localEdit) {
        $source->skills()->sole()->update(['instructions' => 'Local edit.']);
    } else {
        $this->upstreamHead = 'new-upstream';
    }
    $this->postJson("{$this->url}/skill-sources/{$source->id}/upstream", ['fork_sha' => 'sha1', 'upstream_sha' => 'upstream-sha'])->assertUnprocessable();
    Http::assertNotSent(fn (GitHubRequest $request): bool => $request->method() !== 'GET');
})->with([true, false]);

it('keeps local edits when publishing access is revoked', function () {
    $source = importedPublishingSource($this, writable: true);
    $skill = $source->skills()->sole();
    $skill->update(['instructions' => 'My changes.']);
    $this->canPush = false;
    app(SkillSync::class)->sync($source->refresh());
    expect($source->refresh())->status->toBe(SkillSourceStatus::CannotPublish)
        ->and($skill->refresh()->instructions)->toBe('My changes.');
    $this->getJson($this->url.'/skill-sources')->assertOk()->assertJsonPath('data.sources.0.pending_changes.0', 's');
});

it('lets the user resolve a conflict with a combined version', function () {
    $source = importedPublishingSource($this, writable: true);
    $skill = $source->skills()->sole();
    $skill->update(['instructions' => 'My changes.']);
    $this->head = 'sha2';
    $this->files = ['s/SKILL.md' => 'Their changes.'];
    app(SkillSync::class)->sync($source->refresh());
    $this->postJson("{$this->url}/skill-sources/{$source->id}/resolve", ['path' => 's', 'resolution' => 'merged', 'commit_sha' => 'sha2', 'files' => ['SKILL.md' => 'Combined changes.']])->assertOk();
    app(SkillSync::class)->sync($source->refresh());
    expect($source->refresh()->status)->toBe(SkillSourceStatus::Ready)
        ->and($skill->refresh()->instructions)->toBe('Combined changes.');
});

it('enforces workspace and skill management permissions across publishing actions', function () {
    $source = importedPublishingSource($this);
    $skill = $source->skills()->sole();
    $viewer = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    Passport::actingAs($viewer);
    $this->postJson("{$this->url}/skills/{$skill->id}/copy")->assertForbidden();
    $this->postJson("{$this->url}/skills/{$skill->id}/publish", ['repo' => 'me/skills', 'branch' => 'main', 'credential_id' => $this->credential->id, 'path' => 'custom', 'keep_synced' => true])->assertForbidden();
    $this->postJson("{$this->url}/skill-sources/{$source->id}/fork", ['credential_id' => $this->credential->id])->assertForbidden();
    $this->getJson("{$this->url}/skill-sources/{$source->id}/upstream")->assertForbidden();
});

it('keeps a local skill independent when its destination overlaps an existing source', function () {
    importedPublishingSource($this, writable: true);
    $skill = Skill::factory()->create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id]);
    $this->postJson("{$this->url}/skills/{$skill->id}/publish", ['repo' => 'me/skills', 'branch' => 'main', 'credential_id' => $this->credential->id, 'path' => 'new-skill', 'keep_synced' => true])->assertUnprocessable();
    expect($skill->refresh()->isSynced())->toBeFalse();
    Http::assertNotSent(fn (GitHubRequest $request): bool => $request->method() !== 'GET');
});

it('rejects unsafe or incomplete combined files without changing a conflict', function (array $files) {
    $source = importedPublishingSource($this, writable: true);
    $source->skills()->sole()->update(['instructions' => 'Local.']);
    $this->files['s/SKILL.md'] = 'Remote.';
    $this->head = 'sha2';
    app(SkillSync::class)->sync($source, force: true);
    $before = $source->refresh()->sync_conflicts;
    $this->postJson("{$this->url}/skill-sources/{$source->id}/resolve", ['path' => 's', 'resolution' => 'merged', 'commit_sha' => 'sha2', 'files' => $files])->assertUnprocessable();
    expect($source->refresh()->sync_conflicts)->toBe($before);
})->with([
    [['SKILL.md' => 'Combined.', '../escape.md' => 'Unsafe']],
    [['SKILL.md' => '   ']],
    [['SKILL.md' => 'Combined.', 'nested/SKILL.md' => 'Another skill.']],
    [['SKILL.md' => 'Combined.', 'binary.exe' => 'Unsupported']],
    [['ref.md' => 'Missing instructions']],
]);

it('leaves both fork and local content intact when upstream merge conflicts', function () {
    $source = importedPublishingSource($this, writable: true);
    $this->mergeStatus = 409;
    $skill = $source->skills()->sole();
    $this->postJson("{$this->url}/skill-sources/{$source->id}/upstream", ['fork_sha' => 'sha1', 'upstream_sha' => 'upstream-sha'])->assertUnprocessable()->assertJsonPath('message', 'Original updates conflict with your fork. Resolve the merge on GitHub, then sync again.');
    expect($skill->refresh()->instructions)->toBe('Original.')
        ->and($source->refresh()->last_commit_sha)->toBe('sha1');
});

it('continues listing saved edits when a supporting file cannot be published', function () {
    $source = importedPublishingSource($this, writable: true);
    $skill = $source->skills()->sole();
    $skill->references()->create(['title' => '../escape.md', 'content' => 'Kept locally', 'sort_order' => 0]);
    $this->getJson($this->url.'/skill-sources')->assertOk()->assertJsonPath('data.sources.0.pending_changes', ['s']);
    app(SkillSync::class)->sync($source, force: true);
    expect($skill->references()->sole()->content)->toBe('Kept locally')
        ->and($source->refresh()->status)->toBe(SkillSourceStatus::Failed);
});

it('marks lost repository access as cannot publish and preserves local changes', function (int $status) {
    $source = importedPublishingSource($this, writable: true);
    $skill = $source->skills()->sole();
    $skill->update(['instructions' => 'Saved local changes.']);
    $this->readStatus = $status;
    app(SkillSync::class)->sync($source, force: true);
    expect($source->refresh()->status)->toBe(SkillSourceStatus::CannotPublish)
        ->and($skill->refresh()->instructions)->toBe('Saved local changes.');
    $this->getJson($this->url.'/skill-sources')->assertOk()->assertJsonPath('data.sources.0.pending_changes', ['s']);
    Http::assertNotSent(fn (GitHubRequest $request): bool => $request->method() !== 'GET');
})->with([401, 403, 404]);
