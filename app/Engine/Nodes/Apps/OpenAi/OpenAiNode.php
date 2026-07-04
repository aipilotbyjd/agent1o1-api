<?php

namespace App\Engine\Nodes\Apps\OpenAi;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class OpenAiNode extends AppNode
{
    private const BASE_URL = 'https://api.openai.com/v1';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'chat_completion' => $this->chatCompletion($input),
            'embeddings' => $this->embeddings($input),
            'image_generation' => $this->imageGeneration($input),
            default => $this->fail("OpenAi: unknown operation '{$operation}'"),
        };
    }

    private function chatCompletion(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)->post('/chat/completions', array_filter([
            'model' => $input->config['model'] ?? 'gpt-4o-mini',
            'messages' => $input->config['messages'] ?? [
                ['role' => 'user', 'content' => $input->config['prompt'] ?? ''],
            ],
            'max_tokens' => $input->config['max_tokens'] ?? null,
            'temperature' => $input->config['temperature'] ?? null,
        ]));

        if (! $response->successful()) {
            return $this->fail("OpenAI chat_completion failed: {$response->body()}");
        }

        $data = $response->json();

        return $this->success([
            'response' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => $data['usage'] ?? [],
            'model' => $data['model'] ?? '',
        ]);
    }

    private function embeddings(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)->post('/embeddings', [
            'model' => $input->config['model'] ?? 'text-embedding-3-small',
            'input' => $input->config['input'] ?? '',
        ]);

        if (! $response->successful()) {
            return $this->fail("OpenAI embeddings failed: {$response->body()}");
        }

        $data = $response->json();

        return $this->success([
            'embedding' => $data['data'][0]['embedding'] ?? [],
            'usage' => $data['usage'] ?? [],
        ]);
    }

    private function imageGeneration(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)->post('/images/generations', [
            'model' => $input->config['model'] ?? 'dall-e-3',
            'prompt' => $input->config['prompt'] ?? '',
            'n' => (int) ($input->config['n'] ?? 1),
            'size' => $input->config['size'] ?? '1024x1024',
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("OpenAI image_generation failed: {$response->body()}");
    }
}
