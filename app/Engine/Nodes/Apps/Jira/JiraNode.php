<?php

namespace App\Engine\Nodes\Apps\Jira;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;

class JiraNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'create_issue' => $this->createIssue($input),
            'update_issue' => $this->updateIssue($input),
            'get_issue' => $this->getIssue($input),
            'search_issues' => $this->searchIssues($input),
            'add_comment' => $this->addComment($input),
            'transition_issue' => $this->transitionIssue($input),
            default => $this->fail("Jira: unknown operation '{$operation}'"),
        };
    }

    private function jiraHttp(NodeInput $input): PendingRequest
    {
        $domain = $input->credentials['domain'] ?? $input->config['domain'] ?? '';

        return $this->http()
            ->baseUrl("https://{$domain}/rest/api/3")
            ->withBasicAuth(
                $input->credentials['email'] ?? '',
                $input->credentials['api_token'] ?? '',
            );
    }

    private function createIssue(NodeInput $input): NodeResult
    {
        $response = $this->jiraHttp($input)->post('/issue', [
            'fields' => array_filter([
                'project' => ['key' => $input->config['project_key']],
                'summary' => $input->config['summary'],
                'issuetype' => ['name' => $input->config['issue_type'] ?? 'Task'],
                'description' => isset($input->config['description']) ? [
                    'type' => 'doc',
                    'version' => 1,
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => $input->config['description']]],
                    ]],
                ] : null,
            ]),
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Jira create_issue failed: {$response->body()}");
    }

    private function updateIssue(NodeInput $input): NodeResult
    {
        $response = $this->jiraHttp($input)
            ->put("/issue/{$input->config['issue_key']}", [
                'fields' => $input->config['fields'] ?? [],
            ]);

        return $response->successful()
            ? $this->success(['updated' => true])
            : $this->fail("Jira update_issue failed: {$response->body()}");
    }

    private function getIssue(NodeInput $input): NodeResult
    {
        $response = $this->jiraHttp($input)->get("/issue/{$input->config['issue_key']}");

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Jira get_issue failed: {$response->body()}");
    }

    private function searchIssues(NodeInput $input): NodeResult
    {
        $response = $this->jiraHttp($input)->get('/search', [
            'jql' => $input->config['jql'] ?? '',
            'maxResults' => $input->config['max_results'] ?? 25,
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Jira search_issues failed: {$response->body()}");
    }

    private function addComment(NodeInput $input): NodeResult
    {
        $response = $this->jiraHttp($input)
            ->post("/issue/{$input->config['issue_key']}/comment", [
                'body' => [
                    'type' => 'doc',
                    'version' => 1,
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => $input->config['body'] ?? '']],
                    ]],
                ],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Jira add_comment failed: {$response->body()}");
    }

    private function transitionIssue(NodeInput $input): NodeResult
    {
        $response = $this->jiraHttp($input)
            ->post("/issue/{$input->config['issue_key']}/transitions", [
                'transition' => ['id' => $input->config['transition_id']],
            ]);

        return $response->successful()
            ? $this->success(['transitioned' => true])
            : $this->fail("Jira transition_issue failed: {$response->body()}");
    }
}
