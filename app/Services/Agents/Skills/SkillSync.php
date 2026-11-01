<?php

namespace App\Services\Agents\Skills;

use App\Enums\Agents\SkillSourceStatus;
use App\Exceptions\SkillPublishBlocked;
use App\Models\Agents\Skill;
use App\Models\Agents\SkillSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Makes a source's skills match its repository. A check whose branch head
 * hasn't moved since the last sync stops there, unless forced. Otherwise
 * every `SKILL.md` folder is read and its skill added, updated or restored,
 * and skills whose folder is gone are removed. A failed sync leaves the
 * skills as they were.
 */
class SkillSync
{
    public function __construct(
        private readonly GitHubSkillRepository $repository,
        private readonly SkillPackageParser $parser,
    ) {}

    public function sync(SkillSource $source, bool $force = false): void
    {
        $lock = Cache::lock('skill-source:'.$source->id, 360);
        if (! $lock->get()) {
            return;
        }

        try {
            $source->refresh();
            $this->run($source, $force);
        } finally {
            $lock->release();
        }
    }

    private function run(SkillSource $source, bool $force): void
    {
        $source->forceFill(['status' => SkillSourceStatus::Syncing, 'last_error' => null])->save();

        try {
            if ($source->fork_request !== null && ! app(SkillForks::class)->complete($source)) {
                return;
            }
            if ($source->two_way) {
                app(TwoWaySkillSync::class)->sync($source, $this, $force);
                if ($source->publish_once && $source->status === SkillSourceStatus::Ready) {
                    app(SkillSources::class)->disconnect($source, keepSkills: true);
                }

                return;
            }

            $sha = $this->repository->headCommit($source, $this->repository->branch($source));

            if (! $force && $sha === $source->last_commit_sha) {
                $source->forceFill(['status' => SkillSourceStatus::Ready, 'last_synced_at' => now()])->save();

                return;
            }

            $packages = $this->parser->parse($this->repository->files($source, $sha), $source->path);

            DB::transaction(function () use ($source, $packages): void {
                foreach ($packages as $package) {
                    $this->put($source, $package);
                }

                $source->skills()
                    ->whereNotIn('source_path', array_map(fn (SkillPackage $package): string => $package->path, $packages))
                    ->delete();
            });

            $source->forceFill([
                'status' => SkillSourceStatus::Ready,
                'last_commit_sha' => $sha,
                'last_synced_at' => now(),
                'skills_count' => $source->skills()->count(),
            ])->save();
        } catch (Throwable $e) {
            report($e);
            $source->forceFill(['status' => $e instanceof SkillPublishBlocked ? SkillSourceStatus::CannotPublish : SkillSourceStatus::Failed, 'last_error' => Str::limit($e->getMessage(), 500)])->save();
        }
    }

    /**
     * Creates the folder's skill, or brings it up to date: restoring it if
     * the folder had been removed and has come back.
     */
    public function put(SkillSource $source, SkillPackage $package): void
    {
        $skill = Skill::withTrashed()
            ->where('skill_source_id', $source->id)
            ->where('source_path', $package->path)
            ->first();

        if ($skill === null) {
            $skill = new Skill([
                'workspace_id' => $source->workspace_id,
                'created_by' => $source->created_by,
                'slug' => Str::slug($package->name).'-'.Str::random(6),
                'is_shared' => $source->is_shared,
            ]);
            $skill->forceFill(['skill_source_id' => $source->id, 'source_path' => $package->path]);
        } elseif ($skill->trashed()) {
            $skill->restore();
        }

        if ($skill->exists && $skill->instructions !== $package->instructions) {
            $skill->forceFill(['version' => $skill->version + 1]);
        }

        $skill->fill([
            'name' => $package->name,
            'description' => $package->description,
            'instructions' => $package->instructions,
        ])->save();

        $skill->references()->whereNotIn('title', array_column($package->references, 'title'))->delete();
        $skill->scripts()->whereNotIn('name', array_column($package->scripts, 'name'))->delete();

        foreach ($package->references as $sortOrder => $reference) {
            $skill->references()->updateOrCreate(['title' => $reference['title']], ['content' => $reference['content'], 'sort_order' => $sortOrder]);
        }

        foreach ($package->scripts as $script) {
            $skill->scripts()->updateOrCreate(['name' => $script['name']], ['language' => $script['language'], 'code' => $script['code']]);
        }
    }
}
