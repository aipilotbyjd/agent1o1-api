<?php

namespace App\Engine\Nodes\Apps\GitLab;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;

class GitLabNode extends AppNode
{
    public const TYPE = 'gitlab';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'list_issues' => $this->listIssues($input),
            'create_issue' => $this->createIssue($input),
            'update_issue' => $this->updateIssue($input),
            'list_merge_requests' => $this->listMergeRequests($input),
            'create_merge_request' => $this->createMergeRequest($input),
            'trigger_pipeline' => $this->triggerPipeline($input),
            'list_pipelines' => $this->listPipelines($input),
            default => $this->fail("GitLab: unknown operation '{$operation}'"),
        };
    }

    private function gitlabHttp(NodeInput $input): PendingRequest
    {
        $baseUrl = $input->credentials['base_url'] ?? 'https://gitlab.com';

        return $this->http()
            ->baseUrl("{$baseUrl}/api/v4")
            ->withHeaders(['PRIVATE-TOKEN' => $input->credentials['access_token'] ?? $input->credentials['api_key'] ?? '']);
    }

    private function listIssues(NodeInput $input): NodeResult
    {
        $projectId = urlencode($input->config['project_id']);
        $response = $this->gitlabHttp($input)->get("/projects/{$projectId}/issues", array_filter([
            'state' => $input->config['state'] ?? 'opened',
            'per_page' => $input->config['per_page'] ?? 20,
            'labels' => $input->config['labels'] ?? null,
            'assignee_username' => $input->config['assignee'] ?? null,
        ]));

        return $response->successful()
            ? $this->success(['issues' => $response->json()])
            : $this->fail("GitLab list_issues failed: {$response->body()}");
    }

    private function createIssue(NodeInput $input): NodeResult
    {
        $projectId = urlencode($input->config['project_id']);
        $response = $this->gitlabHttp($input)->post("/projects/{$projectId}/issues", array_filter([
            'title' => $input->config['title'],
            'description' => $input->config['description'] ?? null,
            'labels' => $input->config['labels'] ?? null,
            'assignee_ids' => $input->config['assignee_ids'] ?? null,
            'milestone_id' => $input->config['milestone_id'] ?? null,
        ]));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GitLab create_issue failed: {$response->body()}");
    }

    private function updateIssue(NodeInput $input): NodeResult
    {
        $projectId = urlencode($input->config['project_id']);
        $issueIid = $input->config['issue_iid'];
        $response = $this->gitlabHttp($input)->put("/projects/{$projectId}/issues/{$issueIid}", array_filter([
            'title' => $input->config['title'] ?? null,
            'description' => $input->config['description'] ?? null,
            'state_event' => $input->config['state_event'] ?? null,
            'labels' => $input->config['labels'] ?? null,
        ]));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GitLab update_issue failed: {$response->body()}");
    }

    private function listMergeRequests(NodeInput $input): NodeResult
    {
        $projectId = urlencode($input->config['project_id']);
        $response = $this->gitlabHttp($input)->get("/projects/{$projectId}/merge_requests", array_filter([
            'state' => $input->config['state'] ?? 'opened',
            'per_page' => $input->config['per_page'] ?? 20,
        ]));

        return $response->successful()
            ? $this->success(['merge_requests' => $response->json()])
            : $this->fail("GitLab list_merge_requests failed: {$response->body()}");
    }

    private function createMergeRequest(NodeInput $input): NodeResult
    {
        $projectId = urlencode($input->config['project_id']);
        $response = $this->gitlabHttp($input)->post("/projects/{$projectId}/merge_requests", array_filter([
            'title' => $input->config['title'],
            'source_branch' => $input->config['source_branch'],
            'target_branch' => $input->config['target_branch'] ?? 'main',
            'description' => $input->config['description'] ?? null,
            'remove_source_branch' => $input->config['remove_source_branch'] ?? null,
        ]));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GitLab create_merge_request failed: {$response->body()}");
    }

    private function triggerPipeline(NodeInput $input): NodeResult
    {
        $projectId = urlencode($input->config['project_id']);
        $response = $this->gitlabHttp($input)->post("/projects/{$projectId}/trigger/pipeline", array_filter([
            'token' => $input->config['trigger_token'] ?? $input->credentials['trigger_token'] ?? '',
            'ref' => $input->config['ref'] ?? 'main',
            'variables' => $input->config['variables'] ?? null,
        ]));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GitLab trigger_pipeline failed: {$response->body()}");
    }

    private function listPipelines(NodeInput $input): NodeResult
    {
        $projectId = urlencode($input->config['project_id']);
        $response = $this->gitlabHttp($input)->get("/projects/{$projectId}/pipelines", array_filter([
            'status' => $input->config['status'] ?? null,
            'per_page' => $input->config['per_page'] ?? 20,
            'ref' => $input->config['ref'] ?? null,
        ]));

        return $response->successful()
            ? $this->success(['pipelines' => $response->json()])
            : $this->fail("GitLab list_pipelines failed: {$response->body()}");
    }
}
