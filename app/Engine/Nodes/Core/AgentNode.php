<?php

namespace App\Engine\Nodes\Core;

use App\Contracts\NodeHandler;
use App\Engine\NodeInput;
use App\Engine\NodeResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class AgentNode implements NodeHandler
{
    public function handle(NodeInput $input): NodeResult
    {
        $model = $input->config['model'] ?? 'claude-sonnet-4-6';
        $systemPrompt = $input->config['system_prompt'] ?? '';
        $userMessage = $input->config['message'] ?? '';
        $maxTokens = (int) ($input->config['max_tokens'] ?? 4096);
        $temperature = (float) ($input->config['temperature'] ?? 1.0);

        if (empty($userMessage)) {
            return NodeResult::failed('Agent node: message is required');
        }

        $apiKey = $input->credentials['api_key'] ?? config('services.anthropic.key');

        if (empty($apiKey)) {
            return NodeResult::failed('Agent node: Anthropic API key not configured');
        }

        try {
            $messages = [['role' => 'user', 'content' => $userMessage]];

            $payload = [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'messages' => $messages,
            ];

            if (! empty($systemPrompt)) {
                $payload['system'] = $systemPrompt;
            }

            $response = Http::withToken($apiKey)
                ->baseUrl('https://api.anthropic.com/v1')
                ->withHeaders(['anthropic-version' => '2023-06-01'])
                ->post('/messages', $payload);

            if (! $response->successful()) {
                return NodeResult::failed("Anthropic API error: {$response->status()} ".$response->body());
            }

            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '';

            return NodeResult::completed([
                'response' => $content,
                'model' => $data['model'] ?? $model,
                'usage' => $data['usage'] ?? [],
                'stop_reason' => $data['stop_reason'] ?? null,
            ]);
        } catch (Throwable $e) {
            return NodeResult::failed("Agent node failed: {$e->getMessage()}");
        }
    }
}
