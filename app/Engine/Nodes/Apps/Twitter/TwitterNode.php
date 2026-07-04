<?php

namespace App\Engine\Nodes\Apps\Twitter;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class TwitterNode extends AppNode
{
    private const BASE_URL = 'https://api.twitter.com/2';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'post_tweet' => $this->postTweet($input),
            'delete_tweet' => $this->deleteTweet($input),
            'search_tweets' => $this->searchTweets($input),
            'get_user' => $this->getUser($input),
            default => $this->fail("Twitter: unknown operation '{$operation}'"),
        };
    }

    private function postTweet(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/tweets', ['text' => $input->config['text'] ?? '']);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Twitter post_tweet failed: {$response->body()}");
    }

    private function deleteTweet(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->delete("/tweets/{$input->config['tweet_id']}");

        return $response->successful()
            ? $this->success(['deleted' => true])
            : $this->fail("Twitter delete_tweet failed: {$response->body()}");
    }

    private function searchTweets(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get('/tweets/search/recent', [
                'query' => $input->config['query'] ?? '',
                'max_results' => $input->config['max_results'] ?? 10,
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Twitter search_tweets failed: {$response->body()}");
    }

    private function getUser(NodeInput $input): NodeResult
    {
        $username = $input->config['username'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get("/users/by/username/{$username}");

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Twitter get_user failed: {$response->body()}");
    }
}
