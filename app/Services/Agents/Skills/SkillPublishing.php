<?php

namespace App\Services\Agents\Skills;

use App\Models\Agents\Skill;
use App\Models\Agents\SkillSource;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SkillPublishing
{
    public function __construct(
        private readonly SkillSources $sources,
        private readonly GitHubSkillRepository $repository,
        private readonly SkillPackageWriter $writer,
    ) {}

    public function copy(Skill $original, User $user): Skill
    {
        return DB::transaction(function () use ($original, $user): Skill {
            $copy = $original->replicate(['skill_source_id', 'source_path', 'version']);
            $copy->fill(['name' => Str::limit($original->name, 248, '').' (copy)', 'slug' => Str::slug($original->name).'-'.Str::random(6), 'created_by' => $user->id]);
            $copy->forceFill(['origin_url' => $original->origin_url ?? $original->source?->url($original->source_path), 'version' => 1])->save();
            foreach ($original->references as $reference) {
                $copy->references()->create($reference->only(['title', 'content', 'sort_order']));
            }
            foreach ($original->scripts as $script) {
                $copy->scripts()->create($script->only(['name', 'description', 'language', 'code', 'is_enabled']));
            }

            return $copy;
        });
    }

    /** @param array<string, mixed> $data */
    public function access(Workspace $workspace, User $user, array $data): array
    {
        return $this->repository->access($this->sources->unsaved($workspace, $user, $data));
    }

    /** @param array<string, mixed> $data */
    public function publish(Workspace $workspace, User $user, Skill $skill, array $data): SkillSource
    {
        if ($skill->isSynced()) {
            throw ValidationException::withMessages(['skill_id' => 'Make an editable copy before publishing an imported skill.']);
        }
        $candidate = $this->sources->unsaved($workspace, $user, $data);
        try {
            $this->writer->validatePath($data['path']);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['path' => $e->getMessage()]);
        }
        $access = $this->repository->requireWriteAccess($candidate);
        $candidate->branch = $access['branch'];
        $sha = $this->repository->headCommit($candidate, $candidate->branch);
        $candidate->two_way = true;
        $files = $this->repository->files($candidate, $sha);
        foreach (array_keys($files) as $path) {
            if ($path === $data['path'].'/SKILL.md' || (basename($path) === 'SKILL.md' && ($folder = dirname($path)) !== $data['path'] && ($folder === '.' || str_starts_with($data['path'].'/', $folder.'/') || str_starts_with($folder.'/', $data['path'].'/')))) {
                throw ValidationException::withMessages(['path' => 'This folder overlaps an existing skill. Choose a new folder.']);
            }
        }
        if ($workspace->skillSources()->count() >= SkillSources::MAX_PER_WORKSPACE) {
            throw ValidationException::withMessages(['repo' => 'This workspace has reached its repository limit.']);
        }
        foreach ($workspace->skillSources()->whereRaw('lower(repo) = ?', [strtolower($candidate->repo)])->where('branch', $candidate->branch)->get() as $existing) {
            if (blank($existing->path) || $existing->path === $data['path'] || str_starts_with($data['path'].'/', $existing->path.'/') || str_starts_with($existing->path.'/', $data['path'].'/')) {
                throw ValidationException::withMessages(['path' => 'This folder overlaps a connected repository source. Choose a different folder or branch.']);
            }
        }

        return DB::transaction(function () use ($workspace, $candidate, $skill, $data, $access): SkillSource {
            $locked = $workspace->skills()->lockForUpdate()->findOrFail($skill->id);
            if ($locked->isSynced()) {
                throw ValidationException::withMessages(['skill_id' => 'This skill is already connected to a repository.']);
            }
            $candidate->forceFill([
                'path' => $data['path'], 'two_way' => true, 'publish_once' => ! $data['keep_synced'],
                'repository_private' => $access['private'], 'sync_baseline' => [], 'is_shared' => $locked->is_shared,
                'upstream_repo' => $access['parent_repo'], 'upstream_branch' => $access['parent_repo'] ? $access['branch'] : null,
            ])->save();
            $locked->forceFill(['skill_source_id' => $candidate->id, 'source_path' => $data['path']]);
            try {
                $this->writer->files($this->writer->fromSkill($locked));
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['path' => $e->getMessage()]);
            }
            $locked->save();

            return $candidate;
        });
    }
}
