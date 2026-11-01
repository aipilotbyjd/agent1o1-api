<?php

namespace App\Services\Agents\Skills;

use App\Enums\Agents\SkillSourceStatus;
use App\Models\Agents\Skill;
use App\Models\Agents\SkillSource;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Three-way comparison against the last successful sync, at skill granularity. */
class TwoWaySkillSync
{
    public function __construct(
        private readonly GitHubSkillRepository $repository,
        private readonly SkillPackageParser $parser,
        private readonly SkillPackageWriter $writer,
    ) {}

    public function sync(SkillSource $source, SkillSync $importer, bool $force = false): void
    {
        $branch = $this->repository->branch($source);
        $sha = $this->repository->headCommit($source, $branch);
        $local = $this->snapshot($source);
        if (! $force && $sha === $source->last_commit_sha && $local == ($source->sync_baseline ?? []) && empty($source->sync_conflicts)) {
            $source->forceFill(['status' => SkillSourceStatus::Ready, 'last_synced_at' => now()])->save();

            return;
        }
        $files = $this->repository->files($source, $sha);
        $packages = collect($this->parser->parse($files, $source->path, strict: true))->keyBy('path');
        $remote = $packages->map(fn (SkillPackage $package): array => $this->writer->files($package))->all();
        $baseline = $source->sync_baseline ?? [];
        $merged = [];
        $conflicts = [];
        $resolved = [];

        foreach (array_unique([...array_keys($baseline), ...array_keys($local), ...array_keys($remote)]) as $path) {
            $base = $baseline[$path] ?? null;
            $ours = $local[$path] ?? null;
            $theirs = $remote[$path] ?? null;
            if ($ours === $theirs || $theirs === $base) {
                $merged[$path] = $ours;
            } elseif ($ours === $base) {
                $merged[$path] = $theirs;
            } else {
                $previous = collect($source->sync_conflicts ?? [])->firstWhere('path', (string) $path);
                if (($previous['local'] ?? null) === $ours && ($previous['remote'] ?? null) === $theirs && isset($previous['resolution'])) {
                    $merged[$path] = match ($previous['resolution']) {
                        'local' => $ours, 'remote' => $theirs, 'merged' => $previous['merged']
                    };
                    $resolved[] = $previous;
                } else {
                    $conflicts[] = ['path' => (string) $path, 'base' => $base, 'local' => $ours, 'remote' => $theirs, 'commit_sha' => $sha];
                }
            }
        }

        if ($conflicts !== []) {
            $source->forceFill(['status' => SkillSourceStatus::Conflict, 'sync_conflicts' => [...$conflicts, ...$resolved], 'last_error' => 'Both the app and GitHub changed the same skill. Resolve the conflicts before syncing.'])->save();

            return;
        }

        if (count(array_filter($merged, fn (?array $value): bool => $value !== null)) > SkillPackageParser::MAX_SKILLS) {
            throw new RuntimeException('A skill repository source supports at most 100 skills.');
        }

        $changes = [];
        foreach ($merged as $path => $desired) {
            $current = $remote[$path] ?? null;
            if ($desired === $current) {
                continue;
            }
            foreach (array_unique([...array_keys($current ?? []), ...array_keys($desired ?? [])]) as $relative) {
                $fullPath = ltrim($path.'/'.$relative, '/');
                $this->writer->validatePath($fullPath);
                $content = $desired[$relative] ?? null;
                if ($content === ($current[$relative] ?? null)) {
                    continue;
                }
                if ($relative === 'SKILL.md' && $content !== null) {
                    $content = $this->writer->markdown($content, $files[$fullPath] ?? null);
                }
                if (! array_key_exists($relative, $current ?? []) && isset($files[$fullPath]) && $files[$fullPath] !== $content) {
                    throw new RuntimeException("Export would overwrite an unmanaged repository file: {$fullPath}.");
                }
                $changes[$fullPath] = $content;
            }
        }

        $resultFiles = $files;
        foreach ($changes as $path => $content) {
            if ($content === null) {
                unset($resultFiles[$path]);
            } else {
                if (strlen($content) > GitHubSkillRepository::MAX_FILE_BYTES) {
                    throw new RuntimeException('The exported file exceeds 256 KB.');
                }
                $resultFiles[$path] = $content;
            }
        }
        $result = collect($this->parser->parse($resultFiles, $source->path, strict: true))->keyBy('path')
            ->map(fn (SkillPackage $package): array => $this->writer->files($package))->all();
        if ($result != array_filter($merged, fn (?array $value): bool => $value !== null)) {
            throw new RuntimeException('The export overlaps another skill or cannot be imported without losing content.');
        }

        if ($changes !== []) {
            $sha = $this->repository->commit($source, $branch, $sha, $changes);
        }

        DB::transaction(function () use ($source, $importer, $merged, $local, $sha, $branch): void {
            foreach ($merged as $path => $desired) {
                if ($desired === ($local[$path] ?? null)) {
                    continue;
                }
                if ($desired === null) {
                    $source->skills()->where('source_path', $path)->delete();
                } else {
                    $repositoryFiles = [];
                    foreach ($desired as $relative => $content) {
                        $repositoryFiles[ltrim($path.'/'.$relative, '/')] = $content;
                    }
                    $package = $this->parser->parse($repositoryFiles)[0] ?? throw new RuntimeException('The synced skill could not be parsed.');
                    $importer->put($source, $package);
                }
            }
            $source->unsetRelation('skills');
            $source->forceFill([
                'branch' => $branch,
                'sync_baseline' => array_filter($merged, fn (?array $value): bool => $value !== null),
                'sync_conflicts' => null,
                'status' => SkillSourceStatus::Ready,
                'last_error' => null,
                'last_commit_sha' => $sha,
                'last_synced_at' => now(),
                'skills_count' => $source->skills()->count(),
            ])->save();
        });
    }

    /** @return list<string> */
    public function pendingPaths(SkillSource $source): array
    {
        if (! $source->two_way) {
            return [];
        }
        $source->loadMissing(['skills.references', 'skills.scripts']);
        $baseline = $source->sync_baseline ?? [];
        $local = [];
        $invalid = [];
        foreach ($source->skills as $skill) {
            try {
                $local[$skill->source_path] = $this->writer->files($this->writer->fromSkill($skill));
            } catch (RuntimeException) {
                $invalid[] = $skill->source_path;
            }
        }
        $changed = array_filter(array_unique([...array_keys($baseline), ...array_keys($local)]), fn (string $path): bool => ($baseline[$path] ?? null) !== ($local[$path] ?? null));

        return array_values(array_unique(array_map(fn (string|int $path): string => (string) $path, [...$changed, ...$invalid])));
    }

    /** @return array<string, array<string, string>> */
    public function snapshot(SkillSource $source): array
    {
        $source->loadMissing(['skills.references', 'skills.scripts']);

        return $source->skills
            ->mapWithKeys(fn (Skill $skill): array => [$skill->source_path => $this->writer->files($this->writer->fromSkill($skill))])->all();
    }
}
