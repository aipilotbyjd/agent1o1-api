<?php

namespace App\Services;

use App\Models\GitSyncConfig;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Syncs workspace workflows to a Git provider (GitHub) as JSON files via the
 * provider's contents API. Designed to run from a queued job in production.
 */
class GitSyncService
{
    public function __construct(private readonly WorkflowImportExportService $importExport) {}

    /**
     * Push every workflow in the workspace to the configured repository.
     *
     * @return array{pushed: int, failed: int}
     */
    public function export(GitSyncConfig $config): array
    {
        $workspace = $config->workspace;
        $pushed = 0;
        $failed = 0;

        foreach ($workspace->workflows()->with('currentVersion')->get() as $workflow) {
            $payload = $this->importExport->export($workflow);
            $path = trim($config->base_path, '/')."/{$workflow->id}.json";

            try {
                $this->putFile($config, $path, json_encode($payload, JSON_PRETTY_PRINT));
                $pushed++;
            } catch (Throwable) {
                $failed++;
            }
        }

        $config->update(['last_synced_at' => now()]);

        return ['pushed' => $pushed, 'failed' => $failed];
    }

    /**
     * Create or update a file in the repository via the GitHub contents API.
     */
    private function putFile(GitSyncConfig $config, string $path, string $content): void
    {
        $url = "https://api.github.com/repos/{$config->repository}/contents/{$path}";

        $client = Http::withToken($config->access_token)
            ->withHeaders(['Accept' => 'application/vnd.github+json']);

        // Look up an existing file's SHA so we update rather than fail.
        $existing = $client->get($url, ['ref' => $config->branch]);
        $sha = $existing->successful() ? $existing->json('sha') : null;

        $response = $client->put($url, array_filter([
            'message' => "chore: sync workflow {$path}",
            'content' => base64_encode($content),
            'branch' => $config->branch,
            'sha' => $sha,
        ]));

        $response->throw();
    }

    /**
     * Import workflow JSON files from the repository into the workspace.
     *
     * @return int Number of workflows imported.
     */
    public function import(GitSyncConfig $config, Workspace $workspace): int
    {
        $url = "https://api.github.com/repos/{$config->repository}/contents/".trim($config->base_path, '/');

        $response = Http::withToken($config->access_token)
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->get($url, ['ref' => $config->branch]);

        if (! $response->successful()) {
            return 0;
        }

        $imported = 0;

        foreach ($response->json() as $file) {
            if (! str_ends_with($file['name'] ?? '', '.json')) {
                continue;
            }

            $raw = Http::withToken($config->access_token)->get($file['download_url'] ?? '');

            if ($raw->successful() && is_array($payload = $raw->json())) {
                $this->importExport->import($workspace, $config->workspace->owner, $payload);
                $imported++;
            }
        }

        $config->update(['last_synced_at' => now()]);

        return $imported;
    }
}
