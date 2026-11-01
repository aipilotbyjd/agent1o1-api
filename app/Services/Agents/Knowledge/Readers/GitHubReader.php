<?php

namespace App\Services\Agents\Knowledge\Readers;

use App\Enums\Agents\KnowledgeSourceType;
use App\Models\Agents\KnowledgeSource;
use App\Services\Agents\Knowledge\KnowledgeBatch;
use App\Services\Agents\Knowledge\KnowledgeDocumentData;

/**
 * A repository's issues and pull requests, open and closed: the
 * discussion around the code. Unchanged ones are skipped on re-sync.
 */
class GitHubReader implements KnowledgeReader
{
    use ReadsThroughNodes;

    public function type(): KnowledgeSourceType
    {
        return KnowledgeSourceType::GitHub;
    }

    public function configRules(): array
    {
        return ['config.repo' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/']];
    }

    public function read(KnowledgeSource $source, int $limit): KnowledgeBatch
    {
        $issues = $this->node($source, 'github_list_issues', ['repo' => $source->config['repo'], 'state' => 'all', 'per_page' => $limit])['issues'] ?? [];

        return new KnowledgeBatch(collect($issues)
            ->map(fn (array $issue): KnowledgeDocumentData => new KnowledgeDocumentData(
                (string) $issue['number'],
                (isset($issue['pull_request']) ? 'PR' : 'Issue')." #{$issue['number']}: ".($issue['title'] ?? ''),
                'State: '.($issue['state'] ?? 'unknown').'. Opened by '.($issue['user']['login'] ?? 'someone').".\n\n".($issue['body'] ?? ''),
                $issue['html_url'] ?? null,
            ))
            ->all());
    }
}
