<?php

namespace App\Engine\Nodes\Apps\GitHub;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class GitHubNode extends AppNode
{
    private const BASE_URL = 'https://api.github.com';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'list_repos' => $this->listRepos($input),
            'create_issue' => $this->createIssue($input),
            'list_issues' => $this->listIssues($input),
            'create_pull_request' => $this->createPullRequest($input),
            'list_pull_requests' => $this->listPullRequests($input),
            'get_repo', 'get_repository' => $this->getRepository($input),
            'create_comment' => $this->createComment($input),
            'list_commits' => $this->listCommits($input),
            default => $this->fail("GitHub: unknown operation '{$operation}'"),
        };
    }

    private function listRepos(NodeInput $input): NodeResult
    {
        $org = $input->config['owner'] ?? null;

        $response = $org
            ? $this->httpWithAuth($input, self::BASE_URL)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get("/orgs/{$org}/repos", ['per_page' => $input->config['per_page'] ?? 30])
            : $this->httpWithAuth($input, self::BASE_URL)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get('/user/repos', ['per_page' => $input->config['per_page'] ?? 30]);

        return $response->successful()
            ? $this->success(['repos' => $response->json()])
            : $this->fail("GitHub list_repos failed: {$response->body()}");
    }

    private function createIssue(NodeInput $input): NodeResult
    {
        $repo = $input->config['repo'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->post("/repos/{$repo}/issues", [
                'title' => $input->config['title'],
                'body' => $input->config['body'] ?? '',
                'labels' => $input->config['labels'] ?? [],
                'assignees' => $input->config['assignees'] ?? [],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GitHub create_issue failed: {$response->body()}");
    }

    private function listIssues(NodeInput $input): NodeResult
    {
        $repo = $input->config['repo'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get("/repos/{$repo}/issues", [
                'state' => $input->config['state'] ?? 'open',
                'per_page' => $input->config['per_page'] ?? 30,
            ]);

        return $response->successful()
            ? $this->success(['issues' => $response->json()])
            : $this->fail("GitHub list_issues failed: {$response->body()}");
    }

    private function createPullRequest(NodeInput $input): NodeResult
    {
        $repo = $input->config['repo'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post("/repos/{$repo}/pulls", [
                'title' => $input->config['title'],
                'head' => $input->config['head'],
                'base' => $input->config['base'],
                'body' => $input->config['body'] ?? '',
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GitHub create_pr failed: {$response->body()}");
    }

    private function listPullRequests(NodeInput $input): NodeResult
    {
        $repo = $input->config['repo'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->get("/repos/{$repo}/pulls", [
                'state' => $input->config['state'] ?? 'open',
                'per_page' => $input->config['per_page'] ?? 30,
            ]);

        return $response->successful()
            ? $this->success(['pull_requests' => $response->json()])
            : $this->fail("GitHub list_pull_requests failed: {$response->body()}");
    }

    private function getRepository(NodeInput $input): NodeResult
    {
        $repo = $input->config['repo'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get("/repos/{$repo}");

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GitHub get_repository failed: {$response->body()}");
    }

    private function createComment(NodeInput $input): NodeResult
    {
        $repo = $input->config['repo'];
        $issueNumber = $input->config['issue_number'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post("/repos/{$repo}/issues/{$issueNumber}/comments", [
                'body' => $input->config['body'],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GitHub create_comment failed: {$response->body()}");
    }

    private function listCommits(NodeInput $input): NodeResult
    {
        $repo = $input->config['repo'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get("/repos/{$repo}/commits", [
                'sha' => $input->config['branch'] ?? 'main',
                'per_page' => $input->config['per_page'] ?? 10,
            ]);

        return $response->successful()
            ? $this->success(['commits' => $response->json()])
            : $this->fail("GitHub list_commits failed: {$response->body()}");
    }
}
