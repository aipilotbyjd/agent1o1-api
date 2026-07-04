<?php

namespace App\Engine\Nodes\Apps\Twitch;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;

class TwitchNode extends AppNode
{
    private const BASE_URL = 'https://api.twitch.tv/helix';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'get_streams' => $this->getStreams($input),
            'get_user' => $this->getUser($input),
            'get_channel_info' => $this->getChannelInfo($input),
            default => $this->fail("Twitch: unknown operation '{$operation}'"),
        };
    }

    private function twitchHttp(NodeInput $input): PendingRequest
    {
        return $this->httpWithAuth($input, self::BASE_URL)
            ->withHeaders(['Client-Id' => $input->credentials['client_id'] ?? '']);
    }

    private function getStreams(NodeInput $input): NodeResult
    {
        $response = $this->twitchHttp($input)->get('/streams', array_filter([
            'user_login' => $input->config['user_login'] ?? null,
            'game_id' => $input->config['game_id'] ?? null,
            'first' => $input->config['limit'] ?? 20,
        ]));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Twitch get_streams failed: {$response->body()}");
    }

    private function getUser(NodeInput $input): NodeResult
    {
        $response = $this->twitchHttp($input)->get('/users', [
            'login' => $input->config['login'] ?? '',
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Twitch get_user failed: {$response->body()}");
    }

    private function getChannelInfo(NodeInput $input): NodeResult
    {
        $response = $this->twitchHttp($input)->get('/channels', [
            'broadcaster_id' => $input->config['broadcaster_id'] ?? '',
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Twitch get_channel_info failed: {$response->body()}");
    }
}
