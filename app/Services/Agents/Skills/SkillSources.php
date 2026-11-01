<?php

namespace App\Services\Agents\Skills;

use App\Enums\Agents\SkillSourceStatus;
use App\Jobs\Agents\SyncSkillSourceJob;
use App\Models\Agents\Skill;
use App\Models\Agents\SkillSource;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\Knowledge\KnowledgeSources;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Previewing, connecting, re-syncing and disconnecting GitHub repositories
 * as skill sources. `repo` may be `owner/name` or any GitHub link to the
 * repository: a `/tree/{branch}/{folder}` link also sets the branch and
 * folder, unless they're given separately.
 */
class SkillSources
{
    /** Most repositories one workspace may sync skills from. */
    public const int MAX_PER_WORKSPACE = 20;

    public function __construct(
        private readonly KnowledgeSources $accounts,
        private readonly GitHubSkillRepository $repository,
        private readonly SkillPackageParser $parser,
    ) {}

    /**
     * Reads the repository without saving anything, so the member sees which
     * skills it holds before importing them.
     *
     * @param  array{repo?: string, branch?: string|null, path?: string|null, credential_id?: string|null}  $data
     * @return array{repo: string, branch: string, path: string|null, commit_sha: string, skills: list<array{path: string, name: string, description: string|null}>}
     *
     * @throws \RuntimeException when GitHub can't be read
     */
    public function preview(Workspace $workspace, User $user, array $data): array
    {
        $source = $this->unsaved($workspace, $user, $data);

        $branch = $this->repository->branch($source);
        $sha = $this->repository->headCommit($source, $branch);
        $packages = $this->parser->parse($this->repository->files($source, $sha), $source->path);

        return [
            'repo' => $source->repo,
            'branch' => $branch,
            'path' => $source->path,
            'commit_sha' => $sha,
            'skills' => array_map(fn (SkillPackage $package): array => [
                'path' => $package->path,
                'name' => $package->name,
                'description' => $package->description,
            ], $packages),
        ];
    }

    /**
     * Without a `credential_id`, reads with the member's GitHub account if
     * they have one, else anonymously: enough for a public repository.
     *
     * @param  array{repo?: string, branch?: string|null, path?: string|null, credential_id?: string|null, is_shared?: bool}  $data
     */
    public function create(Workspace $workspace, User $user, array $data): SkillSource
    {
        $source = $this->connect($workspace, $user, $data);

        $this->queueSync($source, force: true);

        return $source;
    }

    /**
     * Imports a repository's skills straight away, for an agent or the
     * assistant asked mid-conversation to clone skills from GitHub. A
     * repository folder that's already connected is re-synced rather than
     * connected twice.
     *
     * @param  array{repo?: string, branch?: string|null, path?: string|null}  $data
     *
     * @throws ValidationException when the repository can't be connected
     * @throws \RuntimeException when the sync fails
     */
    public function importNow(Workspace $workspace, User $user, array $data): SkillSource
    {
        $candidate = $this->unsaved($workspace, $user, $data);

        $source = $workspace->skillSources()
            ->whereRaw('lower(repo) = ?', [strtolower($candidate->repo)])
            ->where('path', $candidate->path)
            ->first() ?? $this->connect($workspace, $user, $data);

        app(SkillSync::class)->sync($source, force: true);
        $source->refresh();

        if ($source->status !== SkillSourceStatus::Ready) {
            throw new \RuntimeException($source->last_error ?? 'The skills could not be synced from this repository.');
        }

        return $source;
    }

    /**
     * Saves the source without syncing it.
     *
     * @param  array{repo?: string, branch?: string|null, path?: string|null, credential_id?: string|null, is_shared?: bool, two_way?: bool}  $data
     */
    private function connect(Workspace $workspace, User $user, array $data): SkillSource
    {
        $source = $this->unsaved($workspace, $user, $data);

        if ($workspace->skillSources()->count() >= self::MAX_PER_WORKSPACE) {
            throw ValidationException::withMessages(['repo' => 'This workspace has reached its limit of skill repositories.']);
        }

        if ($workspace->skillSources()->whereRaw('lower(repo) = ?', [strtolower($source->repo)])->where('path', $source->path)->exists()) {
            throw ValidationException::withMessages(['repo' => 'Skills are already synced from this repository folder.']);
        }

        $twoWay = Validator::make($data, ['two_way' => ['sometimes', 'boolean']])->validate()['two_way'] ?? false;
        if ($twoWay && $source->credential === null) {
            throw ValidationException::withMessages(['credential_id' => 'Connect a GitHub account with repository write access to enable two-way sync.']);
        }
        if ($twoWay) {
            $access = $this->requireWritable($source);
            $source->forceFill(['repository_private' => $access['private'], 'branch' => $access['branch'], 'upstream_repo' => $access['parent_repo'], 'upstream_branch' => $access['parent_repo'] ? $access['branch'] : null]);
        }
        $source->forceFill(['is_shared' => (bool) ($data['is_shared'] ?? true), 'two_way' => $twoWay])->save();

        return $source;
    }

    /** @param array{two_way: bool, credential_id?: string|null} $data */
    public function configure(SkillSource $source, User $user, array $data): void
    {
        $credential = filled($data['credential_id'] ?? null)
            ? $this->accounts->credential($source->workspace, $user, 'github', $data['credential_id'])
            : $source->credential;
        if ($data['two_way'] && $credential === null) {
            throw ValidationException::withMessages(['credential_id' => 'Connect a GitHub account with repository write access to enable two-way sync.']);
        }

        if ($source->fork_request !== null) {
            throw ValidationException::withMessages(['two_way' => 'Finish or cancel the pending fork connection first.']);
        }
        if (filled($data['branch'] ?? null) && $data['branch'] !== $source->branch) {
            if ($source->two_way && (app(TwoWaySkillSync::class)->pendingPaths($source) !== [] || filled($source->sync_conflicts))) {
                throw ValidationException::withMessages(['branch' => 'Sync pending edits before choosing a different branch.']);
            }
            $source->forceFill(['branch' => $data['branch'], 'last_commit_sha' => null]);
        }
        if ($data['two_way']) {
            $candidate = clone $source;
            $candidate->setRelation('credential', $credential);
            $access = $this->requireWritable($candidate);
            $source->forceFill(['repository_private' => $access['private'], 'branch' => $access['branch'], 'upstream_repo' => $access['parent_repo'], 'upstream_branch' => $access['parent_repo'] ? $access['branch'] : null]);
        }
        $snapshot = app(TwoWaySkillSync::class)->snapshot($source);
        if ($source->two_way && ! $data['two_way'] && ($snapshot != ($source->sync_baseline ?? []) || filled($source->sync_conflicts))) {
            throw ValidationException::withMessages(['two_way' => 'Sync or resolve pending changes before disabling two-way sync.']);
        }

        $source->forceFill([
            'two_way' => $data['two_way'],
            'connector_credential_id' => $credential?->id,
            'sync_baseline' => $source->two_way ? $source->sync_baseline : $snapshot,
        ])->save();
        $source->unsetRelation('credential');
    }

    public function export(SkillSource $source, Skill $skill, string $path): void
    {
        if (! $source->two_way || $skill->isSynced()) {
            throw ValidationException::withMessages(['skill_id' => 'Choose an unsynced skill and a repository with two-way sync enabled.']);
        }
        $this->requireWritable($source);
        $path = trim($path, '/');
        try {
            app(SkillPackageWriter::class)->validatePath($path);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['path' => $e->getMessage()]);
        }
        $path = ltrim(($source->path ? $source->path.'/' : '').$path, '/');
        foreach ($source->skills()->withTrashed()->pluck('source_path') as $existing) {
            if ($existing === '' || $existing === $path || str_starts_with($path, $existing.'/') || str_starts_with($existing, $path.'/')) {
                throw ValidationException::withMessages(['path' => 'Choose a folder that does not overlap another synced skill.']);
            }
        }
        if ($source->skills()->count() >= SkillPackageParser::MAX_SKILLS) {
            throw ValidationException::withMessages(['skill_id' => 'This source already has 100 skills.']);
        }
        $skill->forceFill(['skill_source_id' => $source->id, 'source_path' => $path]);
        try {
            app(SkillPackageWriter::class)->files(app(SkillPackageWriter::class)->fromSkill($skill));
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['skill_id' => $e->getMessage()]);
        }
        $skill->save();
    }

    public function resolve(SkillSource $source, string $path, string $resolution, string $commit, ?array $files = null): void
    {
        $conflicts = $source->sync_conflicts ?? [];
        $found = false;
        foreach ($conflicts as &$conflict) {
            if ($conflict['path'] === $path && $conflict['commit_sha'] === $commit) {
                if ($resolution === 'merged') {
                    if ($files === null || ! isset($files['SKILL.md'])) {
                        throw ValidationException::withMessages(['files' => 'A combined skill must contain SKILL.md.']);
                    }
                    $repositoryFiles = [];
                    foreach ($files as $relative => $content) {
                        try {
                            app(SkillPackageWriter::class)->validatePath($relative);
                        } catch (\RuntimeException $e) {
                            throw ValidationException::withMessages(['files' => $e->getMessage()]);
                        }
                        $repositoryFiles[ltrim($path.'/'.$relative, '/')] = $content;
                    }
                    try {
                        $packages = $this->parser->parse($repositoryFiles, strict: true);
                    } catch (\RuntimeException $e) {
                        throw ValidationException::withMessages(['files' => $e->getMessage()]);
                    }
                    if (count($packages) !== 1 || $packages[0]->path !== $path || array_diff(array_keys($files), array_keys(app(SkillPackageWriter::class)->files($packages[0]))) !== []) {
                        throw ValidationException::withMessages(['files' => 'Use one complete skill with supported text and script files.']);
                    }
                    $conflict['merged'] = app(SkillPackageWriter::class)->files($packages[0]);
                }
                $conflict['resolution'] = $resolution;
                $found = true;
            }
        }
        unset($conflict);
        if (! $found) {
            throw ValidationException::withMessages(['path' => 'This conflict has changed. Reload the source and choose again.']);
        }
        $source->forceFill(['sync_conflicts' => $conflicts])->save();
    }

    /** @return array<string, mixed> */
    private function requireWritable(SkillSource $source): array
    {
        try {
            return $this->repository->requireWriteAccess($source);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['credential_id' => $e->getMessage()]);
        }
    }

    public function queueSync(SkillSource $source, bool $force = false): void
    {
        $source->forceFill(['status' => SkillSourceStatus::Pending])->save();

        SyncSkillSourceJob::dispatch($source, $force);
    }

    /**
     * Stops syncing. Kept skills become ordinary skills, editable in the app;
     * otherwise they're removed with the source.
     */
    public function disconnect(SkillSource $source, bool $keepSkills): void
    {
        DB::transaction(function () use ($source, $keepSkills): void {
            if ($keepSkills) {
                foreach ($source->skills()->withTrashed()->get() as $skill) {
                    $skill->forceFill(['origin_url' => $skill->origin_url ?? $source->url($skill->source_path), 'skill_source_id' => null, 'source_path' => null])->save();
                }
            } else {
                $source->skills()->delete();
            }

            $source->delete();
        });
    }

    /**
     * The source described by `$data`, validated but not saved.
     *
     * @param  array<string, mixed>  $data
     */
    public function unsaved(Workspace $workspace, User $user, array $data): SkillSource
    {
        $location = $this->location((string) ($data['repo'] ?? ''));

        $validated = Validator::make([
            ...$data,
            'repo' => $location['repo'],
            'branch' => filled($data['branch'] ?? null) ? $data['branch'] : $location['branch'],
            'path' => filled($data['path'] ?? null) ? $data['path'] : $location['path'],
        ], [
            'repo' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'],
            'branch' => ['nullable', 'string', 'max:255', 'regex:/^[^\s~^:?*\[\\\\]+$/'],
            'path' => ['nullable', 'string', 'max:500', 'not_regex:/(^|\/)\.\.(\/|$)/'],
            'credential_id' => ['nullable', 'string'],
        ], [
            'repo.regex' => 'Enter a GitHub repository as owner/name, or paste its link.',
        ])->validate();

        $credential = filled($validated['credential_id'] ?? null)
            ? $this->accounts->credential($workspace, $user, 'github', $validated['credential_id'])
            : $this->accounts->accounts($workspace, $user, 'github')->first();

        $source = $workspace->skillSources()->make([
            'created_by' => $user->id,
            'connector_credential_id' => $credential?->id,
            'repo' => $validated['repo'],
            'branch' => filled($validated['branch'] ?? null) ? trim($validated['branch']) : null,
            'path' => $this->path($validated['path'] ?? null),
        ]);

        return $source->setRelation('credential', $credential);
    }

    /**
     * Reads `owner/name` out of whatever was entered: `owner/name`, a
     * github.com link (with or without `.git`), or a `/tree/` or `/blob/`
     * link, which also names a branch and folder. A branch with a slash in
     * its name can't be told apart from a folder in a link: give it
     * separately.
     *
     * @return array{repo: string, branch: string|null, path: string|null}
     */
    private function location(string $input): array
    {
        $input = trim($input);
        $pattern = '#^(?:(?:https?://)?(?:www\.)?github\.com/|git@github\.com:)?([^/\s]+)/([^/\s?\#]+?)(?:\.git)?(?:/(?:tree|blob)/([^/\s?\#]+)(?:/([^?\#]*))?)?/?(?:[?\#].*)?$#i';

        if (! preg_match($pattern, $input, $match)) {
            return ['repo' => $input, 'branch' => null, 'path' => null];
        }

        $path = isset($match[4]) ? rawurldecode($match[4]) : null;

        // A link to a file (usually a SKILL.md) points at its folder.
        if ($path !== null && preg_match('#(^|/)[^/]+\.[A-Za-z0-9]+$#', $path)) {
            $path = dirname($path) === '.' ? null : dirname($path);
        }

        return [
            'repo' => "{$match[1]}/{$match[2]}",
            'branch' => isset($match[3]) && $match[3] !== '' ? rawurldecode($match[3]) : null,
            'path' => $path,
        ];
    }

    private function path(?string $path): ?string
    {
        $path = trim((string) $path, " /\t");

        return $path === '' ? null : $path;
    }
}
