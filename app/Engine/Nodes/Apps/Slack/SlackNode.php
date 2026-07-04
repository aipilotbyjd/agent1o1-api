<?php

namespace App\Engine\Nodes\Apps\Slack;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class SlackNode extends AppNode
{
    public const TYPE = 'slack';

    private const BASE_URL = 'https://slack.com/api';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'send_message' => $this->sendMessage($input),
            'create_channel' => $this->createChannel($input),
            'invite_to_channel' => $this->inviteToChannel($input),
            'get_channel_history' => $this->getChannelHistory($input),
            'upload_file' => $this->uploadFile($input),
            'list_channels' => $this->listChannels($input),
            'list_users' => $this->listUsers($input),
            default => $this->fail("Slack: unknown operation '{$operation}'"),
        };
    }

    private function sendMessage(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/chat.postMessage', [
                'channel' => $input->config['channel'],
                'text' => $input->config['text'] ?? '',
                'blocks' => $input->config['blocks'] ?? null,
                'username' => $input->config['username'] ?? null,
                'icon_emoji' => $input->config['icon_emoji'] ?? null,
            ]);

        $data = $response->json();

        if (! ($data['ok'] ?? false)) {
            return $this->fail('Slack send_message error: '.($data['error'] ?? 'unknown'));
        }

        return $this->success(['ts' => $data['ts'], 'channel' => $data['channel']]);
    }

    private function createChannel(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/conversations.create', [
                'name' => $input->config['name'],
                'is_private' => $input->config['is_private'] ?? false,
            ]);

        $data = $response->json();

        if (! ($data['ok'] ?? false)) {
            return $this->fail('Slack create_channel error: '.($data['error'] ?? 'unknown'));
        }

        return $this->success($data['channel'] ?? []);
    }

    private function inviteToChannel(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/conversations.invite', [
                'channel' => $input->config['channel'],
                'users' => $input->config['users'],
            ]);

        $data = $response->json();

        return ($data['ok'] ?? false)
            ? $this->success(['invited' => true])
            : $this->fail('Slack invite_to_channel error: '.($data['error'] ?? 'unknown'));
    }

    private function getChannelHistory(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get('/conversations.history', [
                'channel' => $input->config['channel'],
                'limit' => $input->config['limit'] ?? 10,
            ]);

        $data = $response->json();

        return ($data['ok'] ?? false)
            ? $this->success(['messages' => $data['messages'] ?? []])
            : $this->fail('Slack get_channel_history error: '.($data['error'] ?? 'unknown'));
    }

    private function uploadFile(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/files.upload', [
                'channels' => $input->config['channel'],
                'content' => $input->config['content'],
                'filename' => $input->config['filename'] ?? 'file.txt',
            ]);

        $data = $response->json();

        return ($data['ok'] ?? false)
            ? $this->success($data['file'] ?? [])
            : $this->fail('Slack upload_file error: '.($data['error'] ?? 'unknown'));
    }

    private function listChannels(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get('/conversations.list', [
                'limit' => $input->config['limit'] ?? 100,
                'exclude_archived' => $input->config['exclude_archived'] ?? true,
            ]);

        $data = $response->json();

        return ($data['ok'] ?? false)
            ? $this->success(['channels' => $data['channels'] ?? []])
            : $this->fail('Slack list_channels error: '.($data['error'] ?? 'unknown'));
    }

    private function listUsers(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get('/users.list', [
                'limit' => $input->config['limit'] ?? 100,
            ]);

        $data = $response->json();

        return ($data['ok'] ?? false)
            ? $this->success(['members' => $data['members'] ?? []])
            : $this->fail('Slack list_users error: '.($data['error'] ?? 'unknown'));
    }
}
