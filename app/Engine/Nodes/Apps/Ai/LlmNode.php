<?php

namespace App\Engine\Nodes\Apps\Ai;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class LlmNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'generate', 'complete', 'chat' => $this->generate($input),
            'classify' => $this->classify($input),
            'summarize' => $this->summarize($input),
            'sentiment' => $this->sentiment($input),
            'extract' => $this->extract($input),
            default => $this->fail("Llm: unknown operation '{$operation}'"),
        };
    }

    private function generate(NodeInput $input, ?string $systemOverride = null): NodeResult
    {
        $provider = $input->config['provider'] ?? ($input->credentials['provider'] ?? 'anthropic');

        return match ($provider) {
            'anthropic' => $this->anthropic($input, $systemOverride),
            'openai' => $this->openai($input, $systemOverride),
            'gemini' => $this->gemini($input, $systemOverride),
            'azure' => $this->azure($input, $systemOverride),
            'cohere' => $this->cohere($input, $systemOverride),
            'ollama' => $this->ollama($input, $systemOverride),
            'groq' => $this->openaiCompatible($input, $systemOverride, 'Groq', 'https://api.groq.com/openai/v1', 'llama-3.3-70b-versatile', 'GROQ_API_KEY'),
            'mistral' => $this->openaiCompatible($input, $systemOverride, 'Mistral', 'https://api.mistral.ai/v1', 'mistral-small-latest', 'MISTRAL_API_KEY'),
            'deepseek' => $this->openaiCompatible($input, $systemOverride, 'DeepSeek', 'https://api.deepseek.com/v1', 'deepseek-chat', 'DEEPSEEK_API_KEY'),
            'xai' => $this->openaiCompatible($input, $systemOverride, 'xAI', 'https://api.x.ai/v1', 'grok-3-mini', 'XAI_API_KEY'),
            'openrouter' => $this->openaiCompatible($input, $systemOverride, 'OpenRouter', 'https://openrouter.ai/api/v1', 'openai/gpt-4o-mini', 'OPENROUTER_API_KEY'),
            'anyapi' => $this->openaiCompatible($input, $systemOverride, 'AnyAPI', env('ANYAPI_URL', 'https://api.anyapi.ai/v1'), env('ANYAPI_MODEL', 'gpt-4o-mini'), 'ANYAPI_API_KEY'),
            default => $this->fail("Llm: unknown provider '{$provider}'"),
        };
    }

    private function classify(NodeInput $input): NodeResult
    {
        $categories = implode(', ', (array) ($input->config['categories'] ?? []));

        return $this->generate($input, "Classify the user's text into exactly one of these categories: {$categories}. Respond with only the category name.");
    }

    private function summarize(NodeInput $input): NodeResult
    {
        return $this->generate($input, 'Summarize the following text concisely.');
    }

    private function sentiment(NodeInput $input): NodeResult
    {
        return $this->generate($input, 'Analyze the sentiment of the text. Respond with only: positive, negative, or neutral.');
    }

    private function extract(NodeInput $input): NodeResult
    {
        $fields = json_encode($input->config['fields'] ?? []);

        return $this->generate($input, "Extract the following fields from the text and respond with only valid JSON: {$fields}");
    }

    private function anthropic(NodeInput $input, ?string $systemOverride): NodeResult
    {
        $apiKey = $input->credentials['api_key'] ?? env('ANTHROPIC_API_KEY', '');
        $system = $systemOverride ?? ($input->config['system_prompt'] ?? null);

        $response = $this->http()
            ->baseUrl('https://api.anthropic.com/v1')
            ->withHeaders(['x-api-key' => $apiKey, 'anthropic-version' => '2023-06-01'])
            ->post('/messages', array_filter([
                'model' => $input->config['model'] ?? 'claude-sonnet-4-6',
                'max_tokens' => (int) ($input->config['max_tokens'] ?? 4096),
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $input->config['prompt'] ?? '']],
            ]));

        if (! $response->successful()) {
            return $this->fail("Anthropic API error: {$response->body()}");
        }

        $data = $response->json();

        return $this->success([
            'response' => $data['content'][0]['text'] ?? '',
            'usage' => $data['usage'] ?? [],
            'model' => $data['model'] ?? '',
        ]);
    }

    private function openai(NodeInput $input, ?string $systemOverride): NodeResult
    {
        $apiKey = $input->credentials['api_key'] ?? env('OPENAI_API_KEY', '');
        $messages = [];

        if ($system = $systemOverride ?? ($input->config['system_prompt'] ?? null)) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        $messages[] = ['role' => 'user', 'content' => $input->config['prompt'] ?? ''];

        $response = $this->http()
            ->withToken($apiKey)
            ->baseUrl('https://api.openai.com/v1')
            ->post('/chat/completions', [
                'model' => $input->config['model'] ?? 'gpt-4o-mini',
                'messages' => $messages,
            ]);

        if (! $response->successful()) {
            return $this->fail("OpenAI API error: {$response->body()}");
        }

        $data = $response->json();

        return $this->success([
            'response' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => $data['usage'] ?? [],
            'model' => $data['model'] ?? '',
        ]);
    }

    private function gemini(NodeInput $input, ?string $systemOverride): NodeResult
    {
        $apiKey = $input->credentials['api_key'] ?? env('GEMINI_API_KEY', '');
        $model = $input->config['model'] ?? 'gemini-2.0-flash';
        $baseUrl = rtrim(env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/');

        $body = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $input->config['prompt'] ?? '']]],
            ],
        ];

        if ($system = $systemOverride ?? ($input->config['system_prompt'] ?? null)) {
            $body['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        if ($maxTokens = $input->config['max_tokens'] ?? null) {
            $body['generationConfig'] = ['maxOutputTokens' => (int) $maxTokens];
        }

        $response = $this->http()
            ->baseUrl($baseUrl)
            ->post("/models/{$model}:generateContent?key={$apiKey}", $body);

        if (! $response->successful()) {
            return $this->fail("Gemini API error: {$response->body()}");
        }

        $data = $response->json();

        return $this->success([
            'response' => $data['candidates'][0]['content']['parts'][0]['text'] ?? '',
            'usage' => [
                'input_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                'output_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
            ],
            'model' => $model,
        ]);
    }

    private function azure(NodeInput $input, ?string $systemOverride): NodeResult
    {
        $apiKey = $input->credentials['api_key'] ?? env('AZURE_OPENAI_API_KEY', '');
        $endpoint = rtrim($input->credentials['endpoint'] ?? env('AZURE_OPENAI_URL', ''), '/');
        $deployment = $input->credentials['deployment'] ?? env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o');
        $apiVersion = $input->credentials['api_version'] ?? env('AZURE_OPENAI_API_VERSION', '2025-04-01-preview');

        $messages = [];

        if ($system = $systemOverride ?? ($input->config['system_prompt'] ?? null)) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        $messages[] = ['role' => 'user', 'content' => $input->config['prompt'] ?? ''];

        $url = "{$endpoint}/openai/deployments/{$deployment}/chat/completions?api-version={$apiVersion}";

        $response = $this->http()
            ->withHeaders(['api-key' => $apiKey])
            ->post($url, ['messages' => $messages]);

        if (! $response->successful()) {
            return $this->fail("Azure OpenAI API error: {$response->body()}");
        }

        $data = $response->json();

        return $this->success([
            'response' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => $data['usage'] ?? [],
            'model' => $deployment,
        ]);
    }

    private function cohere(NodeInput $input, ?string $systemOverride): NodeResult
    {
        $apiKey = $input->credentials['api_key'] ?? env('COHERE_API_KEY', '');
        $messages = [];

        if ($system = $systemOverride ?? ($input->config['system_prompt'] ?? null)) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        $messages[] = ['role' => 'user', 'content' => $input->config['prompt'] ?? ''];

        $response = $this->http()
            ->withToken($apiKey)
            ->baseUrl('https://api.cohere.com/v2')
            ->post('/chat', [
                'model' => $input->config['model'] ?? 'command-r-plus',
                'messages' => $messages,
            ]);

        if (! $response->successful()) {
            return $this->fail("Cohere API error: {$response->body()}");
        }

        $data = $response->json();

        return $this->success([
            'response' => $data['message']['content'][0]['text'] ?? '',
            'usage' => $data['usage'] ?? [],
            'model' => $data['id'] ?? '',
        ]);
    }

    private function ollama(NodeInput $input, ?string $systemOverride): NodeResult
    {
        $baseUrl = rtrim($input->credentials['url'] ?? env('OLLAMA_URL', 'http://localhost:11434'), '/').'/v1';
        $messages = [];

        if ($system = $systemOverride ?? ($input->config['system_prompt'] ?? null)) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        $messages[] = ['role' => 'user', 'content' => $input->config['prompt'] ?? ''];

        $response = $this->http()
            ->baseUrl($baseUrl)
            ->post('/chat/completions', [
                'model' => $input->config['model'] ?? 'llama3.2',
                'messages' => $messages,
            ]);

        if (! $response->successful()) {
            return $this->fail("Ollama API error: {$response->body()}");
        }

        $data = $response->json();

        return $this->success([
            'response' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => $data['usage'] ?? [],
            'model' => $data['model'] ?? '',
        ]);
    }

    /**
     * Shared handler for OpenAI-compatible providers (Groq, Mistral, DeepSeek, xAI, OpenRouter).
     */
    private function openaiCompatible(
        NodeInput $input,
        ?string $systemOverride,
        string $providerName,
        string $baseUrl,
        string $defaultModel,
        string $envKey,
    ): NodeResult {
        $apiKey = $input->credentials['api_key'] ?? env($envKey, '');
        $messages = [];

        if ($system = $systemOverride ?? ($input->config['system_prompt'] ?? null)) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        $messages[] = ['role' => 'user', 'content' => $input->config['prompt'] ?? ''];

        $response = $this->http()
            ->withToken($apiKey)
            ->baseUrl($baseUrl)
            ->post('/chat/completions', [
                'model' => $input->config['model'] ?? $defaultModel,
                'messages' => $messages,
            ]);

        if (! $response->successful()) {
            return $this->fail("{$providerName} API error: {$response->body()}");
        }

        $data = $response->json();

        return $this->success([
            'response' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => $data['usage'] ?? [],
            'model' => $data['model'] ?? '',
        ]);
    }
}
