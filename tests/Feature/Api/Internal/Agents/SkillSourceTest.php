<?php

use App\Ai\Tools\UpdateSkillTool;
use App\Enums\Agents\SkillSourceStatus;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\Role;
use App\Jobs\Agents\SyncSkillSourceJob;
use App\Models\Agents\Agent;
use App\Models\Agents\Skill;
use App\Models\Agents\SkillSource;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Services\Agents\Skills\SkillSync;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Laravel\Passport\Passport;

/**
 * A gzipped tarball shaped like GitHub's: everything inside one
 * `{owner}-{repo}-{sha}/` folder.
 *
 * @param  array<string, string>  $files
 */
function skillRepoTarball(array $files): string
{
    $dir = sys_get_temp_dir().'/skill-repo-'.uniqid();
    mkdir($dir);
    $tar = new PharData("{$dir}/repo.tar");

    foreach ($files as $path => $content) {
        $tar->addFromString("acme-skills-0123abc/{$path}", $content);
    }

    $tar->compress(Phar::GZ);
    $body = (string) file_get_contents("{$dir}/repo.tar.gz");
    unset($tar);
    array_map(unlink(...), glob("{$dir}/*"));
    rmdir($dir);

    return $body;
}

/**
 * Fakes GitHub for `acme/skills`: the default branch `main`, then each
 * commit in turn with its files.
 *
 * @param  array<string, array<string, string>>  $commits  files keyed by commit sha
 */
function fakeSkillRepo(array $commits): void
{
    $heads = Http::sequence();
    $tarballs = [];

    foreach ($commits as $sha => $files) {
        $heads->push(['name' => 'main', 'commit' => ['sha' => $sha]]);
        $tarballs["https://api.github.com/repos/acme/skills/tarball/{$sha}"] = Http::response(skillRepoTarball($files));
    }

    $heads->whenEmpty(Http::response(['name' => 'main', 'commit' => ['sha' => array_key_last($commits)]]));

    Http::fake([
        'https://api.github.com/repos/acme/skills/branches/main' => $heads,
        ...$tarballs,
        'https://api.github.com/repos/acme/skills' => Http::response(['default_branch' => 'main', 'permissions' => ['push' => true], 'private' => false]),
    ]);
}

const SKILL_MD = <<<'MD'
---
name: weekly-mrr-report
description: >
  Builds the weekly MRR report.
  Load it for revenue updates.
---
# Weekly MRR report

1. Pull subscriptions.
2. Fill `templates/report.md`.
MD;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->url = "/api/v1/workspaces/{$this->workspace->id}/skill-sources";
});

it('imports every SKILL.md folder as a skill with its references and scripts', function () {
    fakeSkillRepo(['sha1' => [
        'README.md' => 'Not a skill.',
        'skills/mrr/SKILL.md' => SKILL_MD,
        'skills/mrr/templates/report.md' => '## MRR: {total}',
        'skills/mrr/scripts/compute.py' => 'print(1)',
        'skills/mrr/logo.png' => "\x89PNG\x00\x01",
        'skills/mrr/nested/SKILL.md' => "---\nname: nested-skill\ndescription: 'Its own skill'\n---\nDo the nested thing.",
        'skills/mrr/nested/notes.md' => 'Nested notes',
        'skills/triage/SKILL.md' => 'Triage tickets by severity.',
    ]]);
    Passport::actingAs($this->owner);

    $this->postJson($this->url, ['repo' => 'acme/skills', 'path' => '/skills/'])
        ->assertCreated()
        ->assertJsonPath('data.source.path', 'skills');

    $source = SkillSource::query()->sole();
    expect($source)->status->toBe(SkillSourceStatus::Ready)->last_commit_sha->toBe('sha1')->skills_count->toBe(3)->connector_credential_id->toBeNull();

    $mrr = $source->skills()->where('source_path', 'skills/mrr')->sole();
    expect($mrr)
        ->name->toBe('weekly-mrr-report')
        ->description->toBe('Builds the weekly MRR report. Load it for revenue updates.')
        ->instructions->toStartWith('# Weekly MRR report')
        ->is_shared->toBeTrue()
        ->created_by->toBe($this->owner->id)
        ->and($mrr->references->pluck('content', 'title')->all())->toBe(['templates/report.md' => '## MRR: {total}'])
        ->and($mrr->scripts()->sole()->only(['name', 'language', 'code']))->toBe(['name' => 'scripts/compute.py', 'language' => 'python', 'code' => 'print(1)']);

    expect($source->skills()->where('source_path', 'skills/mrr/nested')->sole())
        ->name->toBe('nested-skill')->description->toBe('Its own skill')
        ->and($source->skills()->where('source_path', 'skills/mrr/nested')->sole()->references->pluck('title')->all())->toBe(['notes.md']);

    // No frontmatter: named after its folder.
    expect($source->skills()->where('source_path', 'skills/triage')->sole())->name->toBe('triage')->instructions->toBe('Triage tickets by severity.');

    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/skills/{$mrr->id}")
        ->assertJsonPath('data.skill.skill_source_id', $source->id)
        ->assertJsonPath('data.skill.source_url', 'https://github.com/acme/skills/tree/HEAD/skills/mrr');
});

it('re-imports only when the branch moves, then updates, removes and restores skills to match', function () {
    fakeSkillRepo([
        'sha1' => ['a/SKILL.md' => 'Version one.', 'b/SKILL.md' => 'B skill.'],
        'sha2' => ['a/SKILL.md' => 'Version two.', 'a/ref.md' => 'New reference'],
        'sha3' => ['a/SKILL.md' => 'Version two.', 'a/ref.md' => 'New reference', 'b/SKILL.md' => 'B is back.'],
    ]);
    $source = SkillSource::factory()->create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id, 'branch' => null]);
    $sync = app(SkillSync::class);

    $sync->sync($source);
    $a = $source->skills()->where('source_path', 'a')->sole();
    $b = $source->skills()->where('source_path', 'b')->sole();
    $agent = Agent::factory()->create(['workspace_id' => $this->workspace->id]);
    $agent->skills()->attach([$a->id, $b->id]);

    $sync->sync($source->refresh());
    expect($a->refresh())->instructions->toBe('Version two.')->version->toBe(2)
        ->and($a->references()->sole()->title)->toBe('ref.md')
        ->and(Skill::withTrashed()->find($b->id)->trashed())->toBeTrue()
        ->and($source->refresh()->skills_count)->toBe(1);

    $sync->sync($source->refresh());
    expect(Skill::query()->find($b->id))->not->toBeNull()->instructions->toBe('B is back.')
        ->and($a->refresh()->version)->toBe(2)
        ->and($agent->skills()->pluck('skills.id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());

    // The branch hasn't moved: only its head is checked, nothing is downloaded.
    $tarballsBefore = count(Http::recorded(fn (HttpRequest $request): bool => str_contains($request->url(), '/tarball/')));
    $sync->sync($source->refresh());

    expect(Http::recorded(fn (HttpRequest $request): bool => str_contains($request->url(), '/tarball/')))->toHaveCount($tarballsBefore)
        ->and($source->refresh()->status)->toBe(SkillSourceStatus::Ready);
});

it('reads with a connected GitHub account and keeps the skills when a sync fails', function () {
    $connector = Connector::query()->where('key', 'github')->first() ?? Connector::factory()->create(['key' => 'github', 'name' => 'GitHub']);
    $credential = ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($connector)->create([
        'scope' => ConnectorCredentialScope::Personal, 'created_by' => $this->owner->id, 'name' => 'My GitHub', 'data' => ['access_token' => 'gh-token'],
    ]);
    Http::fake([
        'https://api.github.com/repos/acme/skills/branches/main' => Http::sequence()
            ->push(['commit' => ['sha' => 'sha1']])
            ->push(['commit' => ['sha' => 'sha2']]),
        'https://api.github.com/repos/acme/skills/tarball/sha1' => Http::response(skillRepoTarball(['SKILL.md' => "---\nname: Root skill\n---\nAt the root."])),
        'https://api.github.com/repos/acme/skills/tarball/sha2' => Http::response(['message' => 'Not Found'], 404),
    ]);
    Passport::actingAs($this->owner);

    $this->postJson($this->url, ['repo' => 'acme/skills', 'branch' => 'main'])
        ->assertCreated()
        ->assertJsonPath('data.source.account', 'My GitHub');

    Http::assertSent(fn (HttpRequest $request): bool => $request->hasHeader('Authorization', 'Bearer gh-token'));
    $source = SkillSource::query()->sole();
    expect($source->connector_credential_id)->toBe($credential->id)->and($source->skills()->sole()->name)->toBe('Root skill');

    $this->postJson("{$this->url}/{$source->id}/sync")->assertOk();

    expect($source->refresh())->status->toBe(SkillSourceStatus::Failed)
        ->last_error->toContain("can't see it")
        ->and($source->skills()->count())->toBe(1);
});

it('keeps synced skills read-only except for how they look and who sees them', function () {
    fakeSkillRepo(['sha1' => ['s/SKILL.md' => 'Synced.']]);
    $source = SkillSource::factory()->create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id]);
    app(SkillSync::class)->sync($source);
    $skill = $source->skills()->sole();
    $skillUrl = "/api/v1/workspaces/{$this->workspace->id}/skills/{$skill->id}";
    Passport::actingAs($this->owner);

    $this->patchJson($skillUrl, ['instructions' => 'Changed here.'])->assertUnprocessable();
    $this->patchJson($skillUrl, ['icon' => 'Zap', 'color' => '#10A37F', 'is_shared' => false])->assertOk();
    $this->deleteJson($skillUrl)->assertUnprocessable();
    $this->postJson("{$skillUrl}/references", ['title' => 'X', 'content' => 'Y'])->assertUnprocessable();
    $this->postJson("{$skillUrl}/scripts", ['name' => 'x', 'language' => 'bash', 'code' => 'echo'])->assertUnprocessable();

    expect($skill->refresh())->instructions->toBe('Synced.')->icon->toBe('Zap')->is_shared->toBeFalse();

    $agent = Agent::factory()->create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id]);
    $agent->skills()->attach($skill->id);
    $result = (string) (new UpdateSkillTool($agent, $this->owner->id))->handle(new Request(['skill' => $skill->name, 'instructions' => 'Agent edit.']));

    expect($result)->toContain('synced from a GitHub repository')
        ->and($skill->refresh()->instructions)->toBe('Synced.');
});

it('disconnects keeping the skills as editable ones, or removing them', function () {
    fakeSkillRepo(['sha1' => ['s/SKILL.md' => 'Synced.']]);
    Passport::actingAs($this->owner);

    $kept = SkillSource::factory()->create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id]);
    app(SkillSync::class)->sync($kept);
    $keptSkill = $kept->skills()->sole();

    $this->deleteJson("{$this->url}/{$kept->id}?keep_skills=1")->assertNoContent();
    expect($keptSkill->refresh())->isSynced()->toBeFalse()->source_path->toBeNull()
        ->and(SkillSource::query()->count())->toBe(0);
    $this->patchJson("/api/v1/workspaces/{$this->workspace->id}/skills/{$keptSkill->id}", ['instructions' => 'Now mine.'])->assertOk();

    $removed = SkillSource::factory()->create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id, 'path' => 's']);
    app(SkillSync::class)->sync($removed);
    $removedSkill = $removed->skills()->sole();

    $this->deleteJson("{$this->url}/{$removed->id}")->assertNoContent();
    expect(Skill::query()->find($removedSkill->id))->toBeNull()
        ->and(Skill::query()->find($keptSkill->id))->not->toBeNull();
});

it('validates the repository and rejects connecting the same folder twice', function () {
    fakeSkillRepo(['sha1' => ['SKILL.md' => 'Root.']]);
    Passport::actingAs($this->owner);

    $this->postJson($this->url, ['repo' => 'not a repo'])->assertUnprocessable()->assertJsonValidationErrors('repo');
    $this->postJson($this->url, ['repo' => 'acme/skills', 'path' => '../secrets'])->assertUnprocessable()->assertJsonValidationErrors('path');

    $this->postJson($this->url, ['repo' => 'acme/skills'])->assertCreated();
    $this->postJson($this->url, ['repo' => 'Acme/Skills'])->assertUnprocessable()->assertJsonValidationErrors('repo');
});

it('lets only skill managers connect, sync and disconnect repositories', function () {
    $viewer = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    $source = SkillSource::factory()->create(['workspace_id' => $this->workspace->id]);
    Http::preventStrayRequests();
    Passport::actingAs($viewer);

    $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'data.sources');
    $this->postJson($this->url, ['repo' => 'acme/skills'])->assertForbidden();
    $this->postJson("{$this->url}/{$source->id}/sync")->assertForbidden();
    $this->deleteJson("{$this->url}/{$source->id}")->assertForbidden();
});

it('previews the skills a repository holds without saving anything', function () {
    fakeSkillRepo(['sha1' => [
        'skills/mrr/SKILL.md' => SKILL_MD,
        'skills/triage/SKILL.md' => 'Triage tickets.',
        'other/SKILL.md' => 'Outside the folder.',
    ]]);
    Passport::actingAs($this->owner);

    $this->postJson("{$this->url}/preview", ['repo' => 'https://github.com/acme/skills/tree/main/skills'])
        ->assertOk()
        ->assertJsonPath('data.preview.repo', 'acme/skills')
        ->assertJsonPath('data.preview.branch', 'main')
        ->assertJsonPath('data.preview.path', 'skills')
        ->assertJsonPath('data.preview.commit_sha', 'sha1')
        ->assertJsonPath('data.preview.skills', [
            ['path' => 'skills/mrr', 'name' => 'weekly-mrr-report', 'description' => 'Builds the weekly MRR report. Load it for revenue updates.'],
            ['path' => 'skills/triage', 'name' => 'triage', 'description' => null],
        ]);

    expect(SkillSource::query()->count())->toBe(0)->and(Skill::query()->count())->toBe(0);
});

it('explains a repository it cannot read', function () {
    Http::fake(['https://api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);
    Passport::actingAs($this->owner);

    $this->postJson("{$this->url}/preview", ['repo' => 'acme/private'])
        ->assertUnprocessable()
        ->assertJsonPath('message', "Couldn't find acme/private or its branch. A private repository needs a connected GitHub account.");
});

it('reads the repository, branch and folder from a pasted GitHub link', function (string $input, string $repo, ?string $branch, ?string $path) {
    fakeSkillRepo(['sha1' => ['SKILL.md' => 'Root.']]);
    Passport::actingAs($this->owner);

    $this->postJson($this->url, ['repo' => $input])->assertCreated();

    expect(SkillSource::query()->sole()->only(['repo', 'branch', 'path']))->toBe(['repo' => $repo, 'branch' => $branch, 'path' => $path]);
})->with([
    'owner/name' => ['acme/skills', 'acme/skills', null, null],
    'repository link' => ['https://github.com/acme/skills', 'acme/skills', null, null],
    'clone link' => ['https://github.com/acme/skills.git', 'acme/skills', null, null],
    'ssh clone link' => ['git@github.com:acme/skills.git', 'acme/skills', null, null],
    'folder link' => ['github.com/acme/skills/tree/main/skills/sales', 'acme/skills', 'main', 'skills/sales'],
    'SKILL.md link' => ['https://github.com/acme/skills/blob/main/skills/sales/SKILL.md', 'acme/skills', 'main', 'skills/sales'],
]);

/** Connect a writable source while keeping all GitHub traffic fake. */
function writableSkillSource(object $test, array $files): SkillSource
{
    $connector = Connector::query()->where('key', 'github')->first() ?? Connector::factory()->create(['key' => 'github', 'name' => 'GitHub']);
    ConnectorCredential::factory()->forWorkspace($test->workspace)->forConnector($connector)->create([
        'scope' => ConnectorCredentialScope::Personal, 'created_by' => $test->owner->id, 'data' => ['access_token' => 'write-token'],
    ]);
    fakeSkillRepo(['sha1' => $files]);
    Passport::actingAs($test->owner);
    $test->postJson($test->url, ['repo' => 'acme/skills', 'branch' => 'main', 'two_way' => true])->assertCreated();

    return SkillSource::query()->sole();
}

/** @param array<string, string> $files */
function fakeSkillPush(array $files, string $head = 'sha1', int $status = 200): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/repos/acme/skills' => Http::response(['default_branch' => 'main', 'permissions' => ['push' => true], 'private' => false]),
        'https://api.github.com/repos/acme/skills/branches/main' => Http::response(['commit' => ['sha' => $head]]),
        "https://api.github.com/repos/acme/skills/tarball/{$head}" => Http::response(skillRepoTarball($files)),
        "https://api.github.com/repos/acme/skills/git/commits/{$head}" => Http::response(['tree' => ['sha' => 'base-tree']]),
        'https://api.github.com/repos/acme/skills/git/trees' => Http::response(['sha' => 'new-tree']),
        'https://api.github.com/repos/acme/skills/git/commits' => Http::response(['sha' => 'pushed-sha']),
        'https://api.github.com/repos/acme/skills/git/refs/heads/main' => Http::response(['message' => 'Reference update failed'], $status),
    ]);
}

it('pushes app edits including references and scripts in one non-forced commit', function () {
    $files = ['s/SKILL.md' => "---\nname: s\nlicense: MIT\nmetadata:\n  author: Acme\n---\nOriginal.", 'README.md' => 'Leave alone.'];
    $source = writableSkillSource($this, $files);
    $skill = $source->skills()->sole();
    $url = "/api/v1/workspaces/{$this->workspace->id}/skills/{$skill->id}";
    $this->patchJson($url, ['instructions' => 'Edited in app.'])->assertOk();
    $this->postJson("{$url}/references", ['title' => 'examples.md', 'content' => 'Example'])->assertCreated();
    $this->postJson("{$url}/scripts", ['name' => 'run.py', 'language' => 'python', 'code' => 'print(2)'])->assertCreated();
    fakeSkillPush($files);

    $this->postJson("{$this->url}/{$source->id}/sync")->assertOk();

    expect($source->refresh())->status->toBe(SkillSourceStatus::Ready)->last_commit_sha->toBe('pushed-sha');
    Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'PATCH' && $request['force'] === false && $request['sha'] === 'pushed-sha');
    Http::assertSent(function (HttpRequest $request): bool {
        if (! str_ends_with($request->url(), '/git/trees')) {
            return false;
        }
        $files = collect($request['tree'])->pluck('content', 'path')->all();

        return count($files) === 3 && $files['s/examples.md'] === 'Example' && $files['s/run.py'] === 'print(2)'
            && str_contains($files['s/SKILL.md'], 'Edited in app.') && str_contains($files['s/SKILL.md'], 'license: MIT')
            && str_contains($files['s/SKILL.md'], '  author: Acme') && $request['base_tree'] === 'base-tree';
    });
});

it('pulls remote changes and pushes independent local changes together', function () {
    $source = writableSkillSource($this, ['a/SKILL.md' => 'A.', 'b/SKILL.md' => 'B.']);
    $a = $source->skills()->where('source_path', 'a')->sole();
    $a->update(['instructions' => 'Local A.']);
    fakeSkillPush(['a/SKILL.md' => 'A.', 'b/SKILL.md' => 'Remote B.'], 'sha2');

    app(SkillSync::class)->sync($source);

    expect($source->refresh()->status)->toBe(SkillSourceStatus::Ready)
        ->and($a->refresh()->instructions)->toBe('Local A.')
        ->and($source->skills()->where('source_path', 'b')->sole()->instructions)->toBe('Remote B.');
});

it('pauses conflicting edits and applies an explicit resolution', function (string $resolution) {
    $source = writableSkillSource($this, ['s/SKILL.md' => 'Original.']);
    $skill = $source->skills()->sole();
    $skill->update(['instructions' => 'Local.']);
    fakeSkillPush(['s/SKILL.md' => 'Remote.'], 'sha2');
    app(SkillSync::class)->sync($source);

    expect($source->refresh())->status->toBe(SkillSourceStatus::Conflict)->last_commit_sha->toBe('sha1')
        ->and($skill->refresh()->instructions)->toBe('Local.');
    Http::assertNotSent(fn (HttpRequest $request): bool => $request->method() !== 'GET');
    $this->postJson("{$this->url}/{$source->id}/resolve", ['path' => 's', 'resolution' => $resolution, 'commit_sha' => 'sha2'])->assertOk();
    $this->postJson("{$this->url}/{$source->id}/sync")->assertOk();

    expect($source->refresh()->status)->toBe(SkillSourceStatus::Ready)
        ->and($skill->refresh()->instructions)->toBe($resolution === 'local' ? 'Local.' : 'Remote.');
})->with(['local', 'remote']);

it('exports an existing local skill without replacing other repository files', function () {
    $source = writableSkillSource($this, ['README.md' => 'Keep me']);
    $skill = Skill::factory()->create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id]);
    $this->postJson("{$this->url}/{$source->id}/export", ['skill_id' => $skill->id, 'path' => 'skills/custom'])->assertOk();
    fakeSkillPush(['README.md' => 'Keep me']);
    app(SkillSync::class)->sync($source);

    expect($source->refresh()->status)->toBe(SkillSourceStatus::Ready)
        ->and($skill->refresh()->source_path)->toBe('skills/custom');
    Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/git/trees') && count($request['tree']) === 1 && $request['tree'][0]['path'] === 'skills/custom/SKILL.md');
});

it('keeps local edits and the baseline when GitHub rejects a push', function (int $status) {
    $source = writableSkillSource($this, ['s/SKILL.md' => 'Original.']);
    $baseline = $source->sync_baseline;
    $skill = $source->skills()->sole();
    $skill->update(['instructions' => 'Local.']);
    fakeSkillPush(['s/SKILL.md' => 'Original.'], status: $status);
    app(SkillSync::class)->sync($source);

    expect($source->refresh())->status->toBe(in_array($status, [403, 422], true) ? SkillSourceStatus::CannotPublish : SkillSourceStatus::Failed)->last_commit_sha->toBe('sha1')->sync_baseline->toBe($baseline)
        ->and($skill->refresh()->instructions)->toBe('Local.');
})->with([403, 409, 422, 500]);

it('syncs app deletion without deleting unmanaged files', function () {
    $files = ['s/SKILL.md' => 'Original.', 's/run.py' => 'print(1)', 's/image.svg' => '<svg/>'];
    $source = writableSkillSource($this, $files);
    $skill = $source->skills()->sole();
    $this->deleteJson("/api/v1/workspaces/{$this->workspace->id}/skills/{$skill->id}")->assertNoContent();
    fakeSkillPush($files);
    app(SkillSync::class)->sync($source);

    expect($source->refresh())->status->toBe(SkillSourceStatus::Ready)->skills_count->toBe(0);
    Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/git/trees') && count($request['tree']) === 2 && collect($request['tree'])->every(fn (array $entry): bool => array_key_exists('sha', $entry) && $entry['sha'] === null && $entry['path'] !== 's/image.svg'));
});

it('rejects exports across workspaces and unsafe file paths', function () {
    $source = writableSkillSource($this, ['s/SKILL.md' => 'Original.']);
    $foreign = Skill::factory()->create();
    $this->postJson("{$this->url}/{$source->id}/export", ['skill_id' => $foreign->id, 'path' => 'foreign'])->assertNotFound();
    $skill = Skill::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->postJson("{$this->url}/{$source->id}/export", ['skill_id' => $skill->id, 'path' => '../outside'])->assertUnprocessable();
    $skill->references()->create(['title' => '../../outside.md', 'content' => 'Unsafe']);
    $this->postJson("{$this->url}/{$source->id}/export", ['skill_id' => $skill->id, 'path' => 'valid'])->assertUnprocessable();
    expect($skill->refresh()->isSynced())->toBeFalse();
});

it('requires credentials and explicitly enables two-way sync on an existing source', function () {
    Passport::actingAs($this->owner);
    $this->postJson($this->url, ['repo' => 'acme/skills', 'two_way' => true])->assertUnprocessable();
    $source = writableSkillSource($this, ['s/SKILL.md' => 'Original.']);
    $this->patchJson("{$this->url}/{$source->id}", ['two_way' => false])->assertOk();
    $this->patchJson("{$this->url}/{$source->id}", ['two_way' => true])->assertOk();
    $source->skills()->sole()->update(['instructions' => 'Pending.']);
    $this->patchJson("{$this->url}/{$source->id}", ['two_way' => false])->assertUnprocessable();
});

it('does not reuse a conflict resolution after the local skill changes again', function () {
    $source = writableSkillSource($this, ['s/SKILL.md' => 'Original.']);
    $skill = $source->skills()->sole();
    $skill->update(['instructions' => 'Local.']);
    fakeSkillPush(['s/SKILL.md' => 'Remote.'], 'sha2');
    app(SkillSync::class)->sync($source);
    $this->postJson("{$this->url}/{$source->id}/resolve", ['path' => 's', 'resolution' => 'remote', 'commit_sha' => 'sha2'])->assertOk();
    $skill->update(['instructions' => 'New local edit.']);
    app(SkillSync::class)->sync($source);

    expect($source->refresh()->status)->toBe(SkillSourceStatus::Conflict)
        ->and($skill->refresh()->instructions)->toBe('New local edit.');
    Http::assertNotSent(fn (HttpRequest $request): bool => $request->method() !== 'GET');
});

it('pulls a remote deletion but conflicts when the deleted skill was edited locally', function (bool $edit) {
    $source = writableSkillSource($this, ['s/SKILL.md' => 'Original.']);
    $skill = $source->skills()->sole();
    if ($edit) {
        $skill->update(['instructions' => 'Local edit.']);
    }
    fakeSkillPush(['README.md' => 'No skills now.'], 'sha2');
    app(SkillSync::class)->sync($source);

    expect($source->refresh()->status)->toBe($edit ? SkillSourceStatus::Conflict : SkillSourceStatus::Ready)
        ->and($skill->refresh()->trashed())->toBe(! $edit);
})->with([true, false]);

it('blocks concurrent app edits while a source sync holds its lock', function () {
    $source = writableSkillSource($this, ['s/SKILL.md' => 'Original.']);
    $skill = $source->skills()->sole();
    $lock = Cache::lock('skill-source:'.$source->id, 360);
    $lock->get();
    try {
        $this->patchJson("/api/v1/workspaces/{$this->workspace->id}/skills/{$skill->id}", ['instructions' => 'Concurrent.'])->assertConflict();
        $this->deleteJson("{$this->url}/{$source->id}?keep_skills=1")->assertConflict();
    } finally {
        $lock->release();
    }
    expect($skill->refresh()->instructions)->toBe('Original.');
});

it('requires skill management permission for all two-way actions', function () {
    $source = writableSkillSource($this, ['s/SKILL.md' => 'Original.']);
    $viewer = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    Passport::actingAs($viewer);

    $this->patchJson("{$this->url}/{$source->id}", ['two_way' => true])->assertForbidden();
    $this->postJson("{$this->url}/{$source->id}/export", ['skill_id' => $source->skills()->sole()->id, 'path' => 'new'])->assertForbidden();
    $this->postJson("{$this->url}/{$source->id}/resolve", ['path' => 's', 'resolution' => 'local', 'commit_sha' => 'sha1'])->assertForbidden();
});

it('preserves disabled scripts and script identities when pulling updates', function () {
    $source = writableSkillSource($this, ['s/SKILL.md' => 'Original.', 's/run.py' => 'print(1)']);
    $script = $source->skills()->sole()->scripts()->sole();
    $script->update(['is_enabled' => false, 'description' => 'App note']);
    fakeSkillPush(['s/SKILL.md' => 'Updated.', 's/run.py' => 'print(2)'], 'sha2');
    app(SkillSync::class)->sync($source);

    expect($source->refresh()->status)->toBe(SkillSourceStatus::Ready)
        ->and($script->refresh())->is_enabled->toBeFalse()->description->toBe('App note')->code->toBe('print(2)');
});

it('does not download an unchanged two-way repository unless forced', function () {
    $source = writableSkillSource($this, ['s/SKILL.md' => 'Original.']);
    fakeSkillPush(['s/SKILL.md' => 'Original.']);
    app(SkillSync::class)->sync($source);
    Http::assertNotSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/tarball/'));
    app(SkillSync::class)->sync($source, force: true);
    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/tarball/'));
});

it('marks timed out sync jobs as failed so the scheduler can retry them', function () {
    $source = SkillSource::factory()->create(['status' => SkillSourceStatus::Syncing]);
    (new SyncSkillSourceJob($source))->failed(new RuntimeException('Timed out'));
    expect($source->refresh())->status->toBe(SkillSourceStatus::Failed)->last_error->toBe('Timed out');
});

it('retains earlier choices when resolving multiple conflicts over several syncs', function () {
    $source = writableSkillSource($this, ['a/SKILL.md' => 'A.', 'b/SKILL.md' => 'B.']);
    $source->skills()->update(['instructions' => 'Local.']);
    fakeSkillPush(['a/SKILL.md' => 'Remote A.', 'b/SKILL.md' => 'Remote B.'], 'sha2');
    app(SkillSync::class)->sync($source);
    $this->postJson("{$this->url}/{$source->id}/resolve", ['path' => 'a', 'resolution' => 'local', 'commit_sha' => 'sha2'])->assertOk();
    app(SkillSync::class)->sync($source);
    $this->postJson("{$this->url}/{$source->id}/resolve", ['path' => 'b', 'resolution' => 'remote', 'commit_sha' => 'sha2'])->assertOk();
    app(SkillSync::class)->sync($source);

    expect($source->refresh()->status)->toBe(SkillSourceStatus::Ready)
        ->and($source->skills()->where('source_path', 'a')->sole()->instructions)->toBe('Local.')
        ->and($source->skills()->where('source_path', 'b')->sole()->instructions)->toBe('Remote B.');
});

it('fails safely rather than silently truncating an oversized repository package', function () {
    $source = writableSkillSource($this, ['s/SKILL.md' => 'Original.']);
    $files = ['s/SKILL.md' => 'Remote.'];
    foreach (range(1, 31) as $number) {
        $files["s/ref-{$number}.md"] = 'Reference';
    }
    fakeSkillPush($files, 'sha2');
    app(SkillSync::class)->sync($source);

    expect($source->refresh())->status->toBe(SkillSourceStatus::Failed)->last_commit_sha->toBe('sha1')
        ->and($source->skills()->sole()->instructions)->toBe('Original.');
    Http::assertNotSent(fn (HttpRequest $request): bool => $request->method() !== 'GET');
});

it('preserves frontmatter literals when exporting instruction changes', function () {
    $files = ['s/SKILL.md' => "---\nname: s\ndescription: |\n  Existing description.\nlicense: '$1 and \\1'\n---\nOriginal."];
    $source = writableSkillSource($this, $files);
    $source->skills()->sole()->update(['instructions' => 'Updated.']);
    fakeSkillPush($files);
    app(SkillSync::class)->sync($source);

    expect($source->refresh()->status)->toBe(SkillSourceStatus::Ready);
    Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/git/trees') && str_contains($request['tree'][0]['content'], "license: '$1 and \\1'"));
});

it('rejects cross-workspace source configuration and resolution', function () {
    $source = SkillSource::factory()->create();
    Passport::actingAs($this->owner);
    $this->patchJson("{$this->url}/{$source->id}", ['two_way' => true])->assertNotFound();
    $this->postJson("{$this->url}/{$source->id}/resolve", ['path' => 's', 'resolution' => 'local', 'commit_sha' => 'sha1'])->assertNotFound();
});

it('resolves a conflict for a skill at the repository root', function () {
    $source = writableSkillSource($this, ['SKILL.md' => 'Original.']);
    $source->skills()->sole()->update(['instructions' => 'Local.']);
    fakeSkillPush(['SKILL.md' => 'Remote.'], 'sha2');
    app(SkillSync::class)->sync($source);
    $this->postJson("{$this->url}/{$source->id}/resolve", ['path' => '', 'resolution' => 'remote', 'commit_sha' => 'sha2'])->assertOk();
    app(SkillSync::class)->sync($source);

    expect($source->refresh()->status)->toBe(SkillSourceStatus::Ready)
        ->and($source->skills()->sole()->instructions)->toBe('Remote.');
});
