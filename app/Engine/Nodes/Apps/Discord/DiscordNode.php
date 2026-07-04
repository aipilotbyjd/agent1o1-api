<?php

namespace App\Engine\Nodes\Apps\Discord;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class DiscordNode extends AppNode
{
    private const BASE_URL = 'https://discord.com/api/v10';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'send_message' => $this->sendMessage($input),
            'send_webhook' => $this->sendWebhook($input),
            'create_channel' => $this->createChannel($input),
            'get_guild_members' => $this->getGuildMembers($input),
            default => $this->fail("Discord: unknown operation '{$operation}'"),
        };
    }

    private function sendMessage(NodeInput $input): NodeResult
    {
        $channelId = $input->config['channel_id'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post("/channels/{$channelId}/messages", [
                'content' => $input->config['content'],
                'embeds' => $input->config['embeds'] ?? [],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Discord send_message failed: {$response->body()}");
    }

    private function sendWebhook(NodeInput $input): NodeResult
    {
        $webhookUrl = $input->config['webhook_url'] ?? ($input->credentials['webhook_url'] ?? '');
        $response = $this->http()->post($webhookUrl, [
            'content' => $input->config['content'] ?? '',
            'username' => $input->config['username'] ?? null,
            'embeds' => $input->config['embeds'] ?? [],
        ]);

        return $response->successful()
            ? $this->success(['sent' => true])
            : $this->fail("Discord webhook failed: {$response->body()}");
    }

    private function createChannel(NodeInput $input): NodeResult
    {
        $guildId = $input->config['guild_id'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post("/guilds/{$guildId}/channels", [
                'name' => $input->config['name'],
                'type' => $input->config['type'] ?? 0,
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Discord create_channel failed: {$response->body()}");
    }

    private function getGuildMembers(NodeInput $input): NodeResult
    {
        $guildId = $input->config['guild_id'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get("/guilds/{$guildId}/members", ['limit' => $input->config['limit'] ?? 100]);

        return $response->successful()
            ? $this->success(['members' => $response->json()])
            : $this->fail("Discord get_members failed: {$response->body()}");
    }
}
