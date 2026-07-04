<?php

namespace App\Engine\Nodes\Apps\Trello;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class TrelloNode extends AppNode
{
    private const BASE_URL = 'https://api.trello.com/1';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'create_card' => $this->createCard($input),
            'update_card' => $this->updateCard($input),
            'move_card' => $this->moveCard($input),
            'list_cards' => $this->listCards($input),
            'add_comment' => $this->addComment($input),
            default => $this->fail("Trello: unknown operation '{$operation}'"),
        };
    }

    private function authParams(NodeInput $input): array
    {
        return [
            'key' => $input->credentials['api_key'] ?? '',
            'token' => $input->credentials['token'] ?? '',
        ];
    }

    private function createCard(NodeInput $input): NodeResult
    {
        $response = $this->http()->post(self::BASE_URL.'/cards', array_merge($this->authParams($input), array_filter([
            'idList' => $input->config['list_id'],
            'name' => $input->config['name'],
            'desc' => $input->config['description'] ?? null,
            'due' => $input->config['due'] ?? null,
        ])));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Trello create_card failed: {$response->body()}");
    }

    private function updateCard(NodeInput $input): NodeResult
    {
        $response = $this->http()->put(
            self::BASE_URL."/cards/{$input->config['card_id']}",
            array_merge($this->authParams($input), array_filter([
                'name' => $input->config['name'] ?? null,
                'desc' => $input->config['description'] ?? null,
                'closed' => $input->config['closed'] ?? null,
            ])),
        );

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Trello update_card failed: {$response->body()}");
    }

    private function moveCard(NodeInput $input): NodeResult
    {
        $response = $this->http()->put(
            self::BASE_URL."/cards/{$input->config['card_id']}",
            array_merge($this->authParams($input), ['idList' => $input->config['list_id']]),
        );

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Trello move_card failed: {$response->body()}");
    }

    private function listCards(NodeInput $input): NodeResult
    {
        $response = $this->http()->get(
            self::BASE_URL."/lists/{$input->config['list_id']}/cards",
            $this->authParams($input),
        );

        return $response->successful()
            ? $this->success(['cards' => $response->json()])
            : $this->fail("Trello list_cards failed: {$response->body()}");
    }

    private function addComment(NodeInput $input): NodeResult
    {
        $response = $this->http()->post(
            self::BASE_URL."/cards/{$input->config['card_id']}/actions/comments",
            array_merge($this->authParams($input), ['text' => $input->config['text'] ?? '']),
        );

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Trello add_comment failed: {$response->body()}");
    }
}
