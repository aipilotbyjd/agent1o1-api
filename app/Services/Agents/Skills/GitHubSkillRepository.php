<?php

namespace App\Services\Agents\Skills;

use App\Exceptions\SkillPublishBlocked;
use App\Models\Agents\SkillSource;
use App\Services\Connectors\ConnectorTokens;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PharData;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * Reads a skill source's repository from GitHub: the branch's head commit
 * (one call, so an unchanged repository costs nothing more), and the files
 * at that commit as a single tarball: one request however many skills,
 * which keeps an anonymous public-repository sync inside GitHub's 60
 * requests an hour. The archive is read in memory; nothing is extracted.
 */
class GitHubSkillRepository
{
    private const string BASE_URL = 'https://api.github.com';

    /** Largest archive downloaded, in bytes. */
    public const int MAX_ARCHIVE_BYTES = 25 * 1024 * 1024;

    /** Largest single file read from it, in bytes; bigger ones are skipped. */
    public const int MAX_FILE_BYTES = 256 * 1024;

    public function __construct(private readonly ConnectorTokens $tokens) {}

    /**
     * The branch to sync: the source's own, or the repository's default.
     */
    public function branch(SkillSource $source): string
    {
        if ($source->branch !== null && $source->branch !== '') {
            return $source->branch;
        }

        return (string) $this->get($source, "/repos/{$source->repo}")->json('default_branch');
    }

    /**
     * The commit at the head of `$branch`.
     */
    public function headCommit(SkillSource $source, string $branch): string
    {
        $sha = $this->get($source, "/repos/{$source->repo}/branches/".rawurlencode($branch))->json('commit.sha');

        return is_string($sha) && $sha !== '' ? $sha : throw new RuntimeException("GitHub returned no commit for branch \"{$branch}\".");
    }

    /**
     * Every file at `$sha` up to `MAX_FILE_BYTES`, keyed by its path from the
     * repository root.
     *
     * @return array<string, string>
     */
    public function files(SkillSource $source, string $sha): array
    {
        $archive = tempnam(sys_get_temp_dir(), 'skills-').'.tar.gz';

        try {
            $body = $this->request($source)
                ->timeout(120)
                ->get(self::BASE_URL."/repos/{$source->repo}/tarball/{$sha}")
                ->throw(fn (Response $response) => throw $this->failure($source, $response))
                ->body();

            if (strlen($body) > self::MAX_ARCHIVE_BYTES) {
                throw new RuntimeException('The repository is larger than '.(self::MAX_ARCHIVE_BYTES / 1024 / 1024).' MB. Keep skills in a smaller repository.');
            }

            file_put_contents($archive, $body);
            unset($body);

            return $this->read($archive, $source->two_way);
        } finally {
            @unlink($archive);
            @unlink(Str::beforeLast($archive, '.tar.gz'));
        }
    }

    /**
     * GitHub's tarball wraps everything in one `{owner}-{repo}-{sha}/`
     * folder, which is dropped from the paths.
     *
     * @return array<string, string>
     */
    private function read(string $archive, bool $strict = false): array
    {
        try {
            $tar = new PharData($archive);
        } catch (Throwable $e) {
            throw new RuntimeException('GitHub returned an archive that could not be read.', previous: $e);
        }

        $prefix = 'phar://'.$tar->getPath().'/';
        $files = [];

        $entries = new RecursiveIteratorIterator($tar, RecursiveIteratorIterator::LEAVES_ONLY);
        $entries->setMaxDepth(32);

        foreach ($entries as $entry) {
            // Phar does not reliably identify tar symlinks: it can report one
            // as a regular zero-byte file. Skip links before the size check if
            // Phar can identify them on this PHP build.
            if ($entry->isLink()) {
                continue;
            }

            if ($strict && $entry->isFile() && $entry->getSize() > self::MAX_FILE_BYTES) {
                throw new RuntimeException('Two-way sync cannot safely read a repository containing files larger than 256 KB.');
            }
            if (! $entry->isFile() || $entry->getSize() > self::MAX_FILE_BYTES) {
                continue;
            }

            $path = Str::after(Str::after($entry->getPathname(), $prefix), '/');

            if ($path === '') {
                continue;
            }

            try {
                $contents = file_get_contents($entry->getPathname());
            } catch (Throwable $e) {
                // Some Phar builds expose symlinks as unreadable zero-byte
                // entries rather than links. Tar symlink headers have no file
                // payload; regular zero-byte files remain readable as ''.
                if ($entry->getSize() === 0) {
                    continue;
                }

                throw $e;
            }

            if ($contents === false) {
                if ($entry->getSize() === 0) {
                    continue;
                }

                throw new RuntimeException("GitHub returned an unreadable repository file: {$path}.");
            }

            $files[$path] = $contents;
        }

        unset($entries, $tar);

        return $files;
    }

    /** @return array<string, mixed> */
    public function metadata(SkillSource $source): array
    {
        return $this->get($source, "/repos/{$source->repo}")->json();
    }

    /** @return array{repo: string, branch: string, private: bool, can_push: bool, reason: string|null, parent_repo: string|null} */
    public function access(SkillSource $source): array
    {
        $metadata = $this->metadata($source);
        $branch = $source->branch ?: (string) ($metadata['default_branch'] ?? '');
        $protected = (bool) $this->get($source, "/repos/{$source->repo}/branches/".rawurlencode($branch))->json('protected');
        $reason = match (true) {
            $source->credential === null => 'Connect GitHub to publish changes.',
            ! ($metadata['permissions']['push'] ?? false) => 'This account can read this repository but cannot publish to it. Choose your repository or fork this one.',
            (bool) ($metadata['archived'] ?? false), (bool) ($metadata['disabled'] ?? false) => 'This repository is archived or disabled. Choose another repository.',
            $protected => 'This branch is protected. Choose an unprotected branch for automatic publishing.',
            default => null,
        };

        return ['repo' => $source->repo, 'branch' => $branch, 'private' => (bool) ($metadata['private'] ?? false), 'can_push' => $reason === null, 'reason' => $reason, 'parent_repo' => $metadata['parent']['full_name'] ?? null];
    }

    /** @return array{repo: string, branch: string, private: bool, can_push: bool, reason: string|null, parent_repo: string|null} */
    public function requireWriteAccess(SkillSource $source): array
    {
        if ($source->credential === null) {
            throw new SkillPublishBlocked('Connect GitHub to publish changes. Your local edits are saved.');
        }
        $access = $this->access($source);
        if (! $access['can_push']) {
            throw new SkillPublishBlocked($access['reason']);
        }

        return $access;
    }

    public function createFork(SkillSource $source): string
    {
        if ($source->credential === null) {
            throw new SkillPublishBlocked('Connect GitHub before creating a fork.');
        }
        $login = $this->get($source, '/user')->json('login');
        if (! is_string($login) || ! preg_match('/^[A-Za-z0-9-]+$/', $login)) {
            throw new RuntimeException('GitHub returned no account name.');
        }
        if (strcasecmp(Str::before($source->repo, '/'), $login) === 0) {
            throw new RuntimeException('This repository already belongs to your account. Use its sync settings to enable publishing.');
        }
        $fork = $this->write($source, 'POST', 'forks', ['default_branch_only' => false])->json('full_name');
        if (! is_string($fork) || strcasecmp($fork, $login.'/'.Str::after($source->repo, '/')) !== 0) {
            throw new RuntimeException('GitHub did not return the requested fork.');
        }

        return $fork;
    }

    public function forkReady(SkillSource $source): bool
    {
        $response = $this->request($source)->get(self::BASE_URL."/repos/{$source->repo}/branches/".rawurlencode((string) $source->branch));
        if ($response->status() === 404 || $response->status() === 409) {
            return false;
        }
        $response->throw(fn (Response $response) => throw $this->failure($source, $response));

        return filled($response->json('commit.sha'));
    }

    /** @return array<string, mixed> */
    public function compare(SkillSource $source, string $base, string $head): array
    {
        return $this->get($source, "/repos/{$source->repo}/compare/".rawurlencode($base).'...'.rawurlencode($head))->json();
    }

    public function merge(SkillSource $source, string $head): void
    {
        try {
            $this->write($source, 'POST', 'merges', ['base' => $source->branch, 'head' => $head, 'commit_message' => 'Merge reviewed upstream skill updates']);
        } catch (RuntimeException $e) {
            if (str_contains(strtolower($e->getMessage()), 'merge conflict')) {
                throw new RuntimeException('Original updates conflict with your fork. Resolve the merge on GitHub, then sync again.', previous: $e);
            }
            throw $e;
        }
    }

    /**
     * Publish a single commit, preserving unrelated files. A non-fast-forward
     * ref update is rejected by GitHub if somebody pushed while we worked.
     *
     * @param  array<string, string|null>  $changes  null deletes a managed file
     */
    public function commit(SkillSource $source, string $branch, string $parent, array $changes): string
    {
        $this->requireWriteAccess($source);

        $base = $this->get($source, "/repos/{$source->repo}/git/commits/{$parent}")->json('tree.sha');
        if (! is_string($base) || $base === '') {
            throw new RuntimeException('GitHub returned no base tree.');
        }

        $tree = [];
        foreach ($changes as $path => $content) {
            $tree[] = ['path' => $path, 'mode' => '100644', 'type' => 'blob', ...($content === null ? ['sha' => null] : ['content' => $content])];
        }

        $treeSha = $this->write($source, 'POST', 'git/trees', ['base_tree' => $base, 'tree' => $tree])->json('sha');
        if (! is_string($treeSha) || $treeSha === '') {
            throw new RuntimeException('GitHub returned no tree for the skill changes.');
        }
        $sha = $this->write($source, 'POST', 'git/commits', [
            'message' => 'Sync workspace skills', 'tree' => $treeSha, 'parents' => [$parent],
        ])->json('sha');
        if (! is_string($sha) || $sha === '') {
            throw new RuntimeException('GitHub returned no commit for the skill changes.');
        }
        $this->write($source, 'PATCH', 'git/refs/heads/'.str_replace('%2F', '/', rawurlencode($branch)), ['sha' => $sha, 'force' => false]);

        return $sha;
    }

    /** @param array<string, mixed> $data */
    private function write(SkillSource $source, string $method, string $endpoint, array $data): Response
    {
        return $this->request($source)
            ->send($method, self::BASE_URL."/repos/{$source->repo}/{$endpoint}", ['json' => $data])
            ->throw(function (Response $response) use ($source): void {
                if (in_array($response->status(), [401, 403, 404, 422], true) && $response->header('X-RateLimit-Remaining') !== '0') {
                    throw new SkillPublishBlocked('Cannot publish to GitHub: '.Str::limit((string) ($response->json('message') ?? 'Reconnect GitHub or choose a writable repository and branch.'), 300));
                }
                throw $this->failure($source, $response);
            });
    }

    private function get(SkillSource $source, string $endpoint): Response
    {
        return $this->request($source)
            ->get(self::BASE_URL.$endpoint)
            ->throw(fn (Response $response) => throw $this->failure($source, $response));
    }

    private function request(SkillSource $source): PendingRequest
    {
        $request = Http::withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])->connectTimeout(10)->timeout(30);

        return $source->credential === null ? $request : $request->withToken($this->tokens->accessToken($source->credential));
    }

    /**
     * What went wrong, in words someone can act on.
     */
    private function failure(SkillSource $source, Response $response): RuntimeException
    {
        $message = match (true) {
            $response->status() === 404 => $source->credential === null
                ? "Couldn't find {$source->repo} or its branch. A private repository needs a connected GitHub account."
                : "Couldn't find {$source->repo} or its branch, or the connected GitHub account can't see it.",
            $response->status() === 401 => 'GitHub rejected the connected account. Reconnect GitHub in Apps.',
            $response->status() === 403 && $response->header('X-RateLimit-Remaining') === '0' => 'GitHub\'s rate limit was reached. It will sync again later'.($source->credential === null ? '; connect a GitHub account for a higher limit.' : '.'),
            default => 'GitHub error: '.Str::limit((string) ($response->json('message') ?? $response->body()), 200),
        };

        if ($source->two_way && (in_array($response->status(), [401, 404], true) || ($response->status() === 403 && $response->header('X-RateLimit-Remaining') !== '0'))) {
            return new SkillPublishBlocked($message.' Your local edits are saved.');
        }

        return new RuntimeException($message);
    }
}
