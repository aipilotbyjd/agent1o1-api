<?php

namespace App\Engine\Nodes\Apps\Linear;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class LinearNode extends AppNode
{
    private const GRAPHQL_URL = 'https://api.linear.app/graphql';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'create_issue' => $this->createIssue($input),
            'update_issue' => $this->updateIssue($input),
            'list_issues' => $this->listIssues($input),
            'create_comment' => $this->createComment($input),
            default => $this->fail("Linear: unknown operation '{$operation}'"),
        };
    }

    private function graphql(NodeInput $input, string $query, array $variables = []): NodeResult
    {
        $apiKey = $input->credentials['api_key'] ?? '';

        $response = $this->http()
            ->withHeaders(['Authorization' => $apiKey])
            ->post(self::GRAPHQL_URL, ['query' => $query, 'variables' => $variables]);

        $data = $response->json();

        if (! $response->successful() || isset($data['errors'])) {
            return $this->fail('Linear GraphQL error: '.json_encode($data['errors'] ?? $response->body()));
        }

        return $this->success($data['data'] ?? []);
    }

    private function createIssue(NodeInput $input): NodeResult
    {
        $query = <<<'GRAPHQL'
        mutation IssueCreate($input: IssueCreateInput!) {
            issueCreate(input: $input) {
                success
                issue { id identifier title url }
            }
        }
        GRAPHQL;

        return $this->graphql($input, $query, [
            'input' => array_filter([
                'teamId' => $input->config['team_id'],
                'title' => $input->config['title'],
                'description' => $input->config['description'] ?? null,
                'priority' => $input->config['priority'] ?? null,
                'assigneeId' => $input->config['assignee_id'] ?? null,
            ]),
        ]);
    }

    private function updateIssue(NodeInput $input): NodeResult
    {
        $query = <<<'GRAPHQL'
        mutation IssueUpdate($id: String!, $input: IssueUpdateInput!) {
            issueUpdate(id: $id, input: $input) {
                success
                issue { id identifier title url }
            }
        }
        GRAPHQL;

        return $this->graphql($input, $query, [
            'id' => $input->config['issue_id'],
            'input' => array_filter([
                'title' => $input->config['title'] ?? null,
                'description' => $input->config['description'] ?? null,
                'stateId' => $input->config['state_id'] ?? null,
            ]),
        ]);
    }

    private function listIssues(NodeInput $input): NodeResult
    {
        $query = <<<'GRAPHQL'
        query Issues($first: Int) {
            issues(first: $first) {
                nodes { id identifier title state { name } url }
            }
        }
        GRAPHQL;

        return $this->graphql($input, $query, ['first' => (int) ($input->config['limit'] ?? 25)]);
    }

    private function createComment(NodeInput $input): NodeResult
    {
        $query = <<<'GRAPHQL'
        mutation CommentCreate($input: CommentCreateInput!) {
            commentCreate(input: $input) { success }
        }
        GRAPHQL;

        return $this->graphql($input, $query, [
            'input' => [
                'issueId' => $input->config['issue_id'],
                'body' => $input->config['body'],
            ],
        ]);
    }
}
