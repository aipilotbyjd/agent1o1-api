<?php

namespace App\Services\Agents\Skills;

use App\Enums\Agents\SkillSourceStatus;
use App\Models\Agents\SkillSource;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Services\Agents\Knowledge\KnowledgeSources;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Fork creation is asynchronous: the source switches only after readiness checks. */
class SkillForks
{
    public function __construct(
        private readonly KnowledgeSources $accounts,
        private readonly GitHubSkillRepository $repository,
        private readonly TwoWaySkillSync $sync,
        private readonly SkillPackageParser $parser,
    ) {}

    public function begin(SkillSource $source, User $user, string $credentialId, ?string $existingFork): void
    {
        if ($source->two_way || $source->fork_request !== null || $source->last_commit_sha === null) {
            throw ValidationException::withMessages(['repo' => 'Finish importing this repository before creating a fork. Two-way sources already have a publishing destination.']);
        }
        $credential = $this->accounts->credential($source->workspace, $user, 'github', $credentialId);
        $original = clone $source;
        $original->setRelation('credential', $credential);
        $branch = $this->repository->branch($original);
        $repo = $existingFork ?? $this->repository->createFork($original);
        $source->forceFill([
            'fork_request' => ['repo' => $repo, 'branch' => $branch, 'credential_id' => $credential->id],
            'status' => SkillSourceStatus::Forking, 'last_error' => null,
        ])->save();
    }

    public function complete(SkillSource $source): bool
    {
        $request = $source->fork_request;
        if ($request === null) {
            return true;
        }
        $credential = ConnectorCredential::query()->where('workspace_id', $source->workspace_id)->find($request['credential_id']);
        if ($credential === null || ! $credential->isUsable()) {
            throw new RuntimeException('Reconnect the GitHub account used to create the fork, or cancel this fork connection.');
        }
        $fork = clone $source;
        $fork->forceFill(['repo' => $request['repo'], 'branch' => $request['branch'], 'two_way' => true])->setRelation('credential', $credential);
        if (! $this->repository->forkReady($fork)) {
            $source->forceFill(['status' => SkillSourceStatus::Forking, 'last_error' => null])->save();

            return false;
        }
        $access = $this->repository->requireWriteAccess($fork);
        if (strcasecmp((string) $access['parent_repo'], $source->repo) !== 0) {
            throw new RuntimeException('Choose a direct fork of '.$source->repo.'. The original connection is unchanged.');
        }
        if (SkillSource::query()->where('workspace_id', $source->workspace_id)->whereKeyNot($source->id)->whereRaw('lower(repo) = ?', [strtolower($fork->repo)])->where('path', $source->path)->exists()) {
            throw new RuntimeException('This fork folder is already connected to this workspace.');
        }
        $sha = $this->repository->headCommit($fork, $fork->branch);
        $this->parser->parse($this->repository->files($fork, $sha), $source->path, strict: true);
        DB::transaction(function () use ($source, $fork, $access, $credential): void {
            foreach ($source->skills as $skill) {
                if ($skill->origin_url === null) {
                    $skill->forceFill(['origin_url' => $source->url($skill->source_path)])->save();
                }
            }
            $source->forceFill([
                'upstream_repo' => $source->repo, 'upstream_branch' => $fork->branch,
                'repo' => $fork->repo, 'branch' => $fork->branch, 'two_way' => true,
                'connector_credential_id' => $credential->id, 'repository_private' => $access['private'],
                'sync_baseline' => $this->sync->snapshot($source), 'sync_conflicts' => null,
                'fork_request' => null, 'last_commit_sha' => null,
                'status' => SkillSourceStatus::Ready, 'last_error' => null,
            ])->save();
        });
        $source->unsetRelation('credential');

        return true;
    }

    public function cancel(SkillSource $source): void
    {
        if ($source->fork_request === null) {
            return;
        }
        $source->forceFill(['fork_request' => null, 'status' => SkillSourceStatus::Ready, 'last_error' => null])->save();
    }

    /** @return array<string, mixed> */
    public function upstream(SkillSource $source): array
    {
        if ($source->upstream_repo === null || $source->upstream_branch === null) {
            throw new RuntimeException('This source has no original repository to check.');
        }
        $original = clone $source;
        $original->repo = $source->upstream_repo;
        $original->branch = $source->upstream_branch;
        $forkSha = $this->repository->headCommit($source, (string) $source->branch);
        $upstreamSha = $this->repository->headCommit($original, $original->branch);
        $comparison = $this->repository->compare($source, $forkSha, $upstreamSha);

        return [
            'repo' => $source->upstream_repo, 'fork_sha' => $forkSha, 'upstream_sha' => $upstreamSha,
            'commits_ahead' => (int) ($comparison['ahead_by'] ?? 0),
            'files' => array_map(fn (array $file): array => ['path' => $file['filename'], 'status' => $file['status'], 'patch' => $file['patch'] ?? null], $comparison['files'] ?? []),
            'files_truncated' => count($comparison['files'] ?? []) >= 300,
        ];
    }

    public function applyUpstream(SkillSource $source, string $forkSha, string $upstreamSha): void
    {
        if ($this->sync->pendingPaths($source) !== [] || filled($source->sync_conflicts)) {
            throw ValidationException::withMessages(['fork_sha' => 'Sync local edits and resolve conflicts before updating from the original repository.']);
        }
        $access = $this->repository->requireWriteAccess($source);
        if ($source->upstream_repo === null || strcasecmp((string) $access['parent_repo'], $source->upstream_repo) !== 0) {
            throw new RuntimeException('This repository is no longer a fork of the recorded original.');
        }
        $preview = $this->upstream($source);
        if ($preview['fork_sha'] !== $forkSha || $preview['upstream_sha'] !== $upstreamSha) {
            throw ValidationException::withMessages(['upstream_sha' => 'The repositories changed after this review. Check for updates again.']);
        }
        if ($preview['commits_ahead'] > 0) {
            $this->repository->merge($source, $upstreamSha);
        }
    }
}
