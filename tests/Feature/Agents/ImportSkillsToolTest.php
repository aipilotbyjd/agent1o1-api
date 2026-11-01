<?php

use App\Ai\Assistant\Tools\ImportSkillsTool as AssistantImportSkillsTool;
use App\Ai\Tools\ImportSkillsTool;
use App\Enums\Agents\SkillSourceStatus;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Agents\SkillSource;
use App\Models\Assistant\Assistant;
use App\Models\User;
use App\Services\Agents\Skills\SkillSources;
use App\Services\Assistant\Tools\ToolCatalog;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

/**
 * Fakes GitHub for `acme/skills` at commit `sha1` holding `$files`.
 *
 * @param  array<string, string>  $files
 */
function fakeImportableSkillRepo(array $files): void
{
    $dir = sys_get_temp_dir().'/import-skill-repo-'.uniqid();
    mkdir($dir);
    $tar = new PharData("{$dir}/repo.tar");
    foreach ($files as $path => $content) {
        $tar->addFromString("acme-skills-sha1/{$path}", $content);
    }
    $tar->compress(Phar::GZ);
    $tarball = (string) file_get_contents("{$dir}/repo.tar.gz");
    unset($tar);
    array_map(unlink(...), glob("{$dir}/*"));
    rmdir($dir);

    Http::fake([
        'https://api.github.com/repos/acme/skills/branches/main' => Http::response(['name' => 'main', 'commit' => ['sha' => 'sha1']]),
        'https://api.github.com/repos/acme/skills/tarball/sha1' => Http::response($tarball),
        'https://api.github.com/repos/acme/skills' => Http::response(['default_branch' => 'main', 'private' => false]),
    ]);
}

beforeEach(function () {
    Http::preventStrayRequests();
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create(['allow_skill_editing' => true]);

    fakeImportableSkillRepo([
        'skills/mrr/SKILL.md' => "---\nname: Weekly MRR\ndescription: Builds the MRR report.\n---\nPull subscriptions.",
        'skills/tone/SKILL.md' => "---\nname: Tone\ndescription: How we write.\n---\nBe brief.",
    ]);
});

it('lets an agent clone skills from a GitHub link and attaches them to itself', function () {
    $result = (string) (new ImportSkillsTool($this->agent, $this->owner->id))->handle(new Request([
        'repo' => 'https://github.com/acme/skills/tree/main/skills',
    ]));

    $source = SkillSource::query()->sole();
    expect($result)->toContain('Imported from acme/skills and attached to you: ');
    expect($source->status)->toBe(SkillSourceStatus::Ready);
    expect($source->path)->toBe('skills');
    expect($this->agent->fresh()->skills->pluck('name')->sort()->values()->all())->toBe(['Tone', 'Weekly MRR']);
});

it('attaches only the skills the agent asked for', function () {
    (new ImportSkillsTool($this->agent, $this->owner->id))->handle(new Request([
        'repo' => 'acme/skills',
        'skills' => ['weekly mrr'],
    ]));

    expect($this->workspace->skills()->count())->toBe(2);
    expect($this->agent->fresh()->skills->pluck('name')->all())->toBe(['Weekly MRR']);
});

it('re-syncs a repository that is already connected instead of connecting it twice', function () {
    $tool = new ImportSkillsTool($this->agent, $this->owner->id);

    $tool->handle(new Request(['repo' => 'acme/skills']));
    $result = (string) $tool->handle(new Request(['repo' => 'https://github.com/acme/skills.git']));

    expect($result)->toContain('Imported from acme/skills');
    expect(SkillSource::query()->count())->toBe(1);
    expect($this->workspace->skills()->count())->toBe(2);
});

it('does not import for someone who cannot manage skills', function () {
    $viewer = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);

    $result = (string) (new ImportSkillsTool($this->agent, $viewer->id))->handle(new Request(['repo' => 'acme/skills']));

    expect($result)->toStartWith('Not imported: the person you are working for is not allowed');
    expect(SkillSource::query()->count())->toBe(0);
});

it('explains a repository it cannot read', function () {
    Http::fake(['https://api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

    $result = (string) (new ImportSkillsTool($this->agent, $this->owner->id))->handle(new Request(['repo' => 'acme/missing']));

    expect($result)->toStartWith('Not imported: ');
    expect($this->agent->fresh()->skills)->toBeEmpty();
});

it('lets the assistant clone skills into the workspace library', function () {
    $assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);

    $result = (string) (new AssistantImportSkillsTool($assistant, app(SkillSources::class)))->handle(new Request(['repo' => 'acme/skills']));

    expect($result)->toContain('Imported 2 skill(s) from acme/skills');
    expect($this->workspace->skills()->pluck('name')->sort()->values()->all())->toBe(['Tone', 'Weekly MRR']);
    expect(collect(app(ToolCatalog::class)->available($assistant))->map->name())->toContain(AssistantImportSkillsTool::NAME);
});
