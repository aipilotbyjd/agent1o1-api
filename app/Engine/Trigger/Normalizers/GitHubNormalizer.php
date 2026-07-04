<?php

namespace App\Engine\Trigger\Normalizers;

use App\Contracts\PayloadNormalizer;

class GitHubNormalizer implements PayloadNormalizer
{
    public function provider(): string
    {
        return 'github';
    }

    public function normalize(array $raw, string $providerEvent): array
    {
        $data = match ($providerEvent) {
            'push' => $this->normalizePush($raw),
            'pull_request' => $this->normalizePullRequest($raw),
            'issues' => $this->normalizeIssue($raw),
            'release' => $this->normalizeRelease($raw),
            default => $raw,
        };

        return [
            'trigger' => ['provider' => 'github', 'event' => $providerEvent],
            'data' => $data,
            'meta' => [
                'received_at' => now()->toIso8601String(),
                'delivery_id' => $raw['_delivery_id'] ?? null,
            ],
        ];
    }

    public function extractDedupKey(array $raw, array $headers = []): ?string
    {
        return $headers['X-GitHub-Delivery'] ?? $headers['x-github-delivery'] ?? null;
    }

    private function normalizePush(array $raw): array
    {
        return [
            'ref' => $raw['ref'] ?? null,
            'before' => $raw['before'] ?? null,
            'after' => $raw['after'] ?? null,
            'commits' => array_map(fn ($c) => [
                'id' => $c['id'] ?? null,
                'message' => $c['message'] ?? null,
                'author' => $c['author']['name'] ?? null,
                'url' => $c['url'] ?? null,
            ], $raw['commits'] ?? []),
            'pusher' => $raw['pusher'] ?? null,
            'repository' => [
                'full_name' => $raw['repository']['full_name'] ?? null,
                'private' => $raw['repository']['private'] ?? false,
                'url' => $raw['repository']['html_url'] ?? null,
            ],
        ];
    }

    private function normalizePullRequest(array $raw): array
    {
        $pr = $raw['pull_request'] ?? [];

        return [
            'action' => $raw['action'] ?? null,
            'number' => $raw['number'] ?? null,
            'title' => $pr['title'] ?? null,
            'state' => $pr['state'] ?? null,
            'url' => $pr['html_url'] ?? null,
            'author' => $pr['user']['login'] ?? null,
            'base' => $pr['base']['ref'] ?? null,
            'head' => $pr['head']['ref'] ?? null,
            'merged' => $pr['merged'] ?? false,
            'repository' => [
                'full_name' => $raw['repository']['full_name'] ?? null,
            ],
        ];
    }

    private function normalizeIssue(array $raw): array
    {
        $issue = $raw['issue'] ?? [];

        return [
            'action' => $raw['action'] ?? null,
            'number' => $issue['number'] ?? null,
            'title' => $issue['title'] ?? null,
            'state' => $issue['state'] ?? null,
            'url' => $issue['html_url'] ?? null,
            'author' => $issue['user']['login'] ?? null,
            'labels' => array_map(fn ($l) => $l['name'], $issue['labels'] ?? []),
            'repository' => [
                'full_name' => $raw['repository']['full_name'] ?? null,
            ],
        ];
    }

    private function normalizeRelease(array $raw): array
    {
        $release = $raw['release'] ?? [];

        return [
            'action' => $raw['action'] ?? null,
            'tag_name' => $release['tag_name'] ?? null,
            'name' => $release['name'] ?? null,
            'prerelease' => $release['prerelease'] ?? false,
            'draft' => $release['draft'] ?? false,
            'url' => $release['html_url'] ?? null,
            'author' => $release['author']['login'] ?? null,
            'repository' => [
                'full_name' => $raw['repository']['full_name'] ?? null,
            ],
        ];
    }
}
