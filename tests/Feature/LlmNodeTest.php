<?php

use App\Engine\NodeInput;
use App\Engine\Nodes\Apps\Ai\LlmNode;
use App\Enums\ExecutionNodeStatus;
use Illuminate\Support\Facades\Http;

function makeLlmInput(array $config, array $credentials = []): NodeInput
{
    return new NodeInput(
        nodeId: 'test-node',
        nodeType: 'ai.llm',
        nodeName: 'LLM',
        config: $config,
        inputData: [],
        credentials: $credentials,
        variables: [],
        executionMeta: [],
    );
}

function llmNode(): LlmNode
{
    return new LlmNode;
}

// --- Anthropic ---

test('anthropic generate returns response text', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => 'Hello from Anthropic']],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            'model' => 'claude-sonnet-4-6',
        ]),
    ]);

    $result = llmNode()->handle(makeLlmInput([
        'operation' => 'generate',
        'provider' => 'anthropic',
        'prompt' => 'Say hello',
    ], ['api_key' => 'test-key']));

    expect($result->status)->toBe(ExecutionNodeStatus::Completed)
        ->and($result->output['response'])->toBe('Hello from Anthropic');
});

test('anthropic returns failure on API error', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => ['message' => 'Unauthorized']], 401),
    ]);

    $result = llmNode()->handle(makeLlmInput([
        'operation' => 'generate',
        'provider' => 'anthropic',
        'prompt' => 'Hello',
    ], ['api_key' => 'bad-key']));

    expect($result->status)->toBe(ExecutionNodeStatus::Failed);
});

// --- OpenAI ---

test('openai generate returns response text', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello from OpenAI']]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            'model' => 'gpt-4o-mini',
        ]),
    ]);

    $result = llmNode()->handle(makeLlmInput([
        'operation' => 'generate',
        'provider' => 'openai',
        'prompt' => 'Say hello',
    ], ['api_key' => 'test-key']));

    expect($result->status)->toBe(ExecutionNodeStatus::Completed)
        ->and($result->output['response'])->toBe('Hello from OpenAI');
});

// --- Gemini ---

test('gemini generate returns response text', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'Hello from Gemini']]]],
            ],
            'usageMetadata' => ['promptTokenCount' => 8, 'candidatesTokenCount' => 4],
        ]),
    ]);

    $result = llmNode()->handle(makeLlmInput([
        'operation' => 'generate',
        'provider' => 'gemini',
        'model' => 'gemini-2.0-flash',
        'prompt' => 'Say hello',
    ], ['api_key' => 'test-key']));

    expect($result->status)->toBe(ExecutionNodeStatus::Completed)
        ->and($result->output['response'])->toBe('Hello from Gemini')
        ->and($result->output['usage']['input_tokens'])->toBe(8);
});

test('gemini sends system instruction when provided', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'Formal reply']]]],
            ],
            'usageMetadata' => [],
        ]),
    ]);

    llmNode()->handle(makeLlmInput([
        'operation' => 'generate',
        'provider' => 'gemini',
        'prompt' => 'Hello',
        'system_prompt' => 'Be formal.',
    ], ['api_key' => 'test-key']));

    Http::assertSent(function ($request) {
        $body = $request->data();

        return isset($body['systemInstruction']['parts'][0]['text']);
    });
});

// --- Cohere ---

test('cohere generate returns response text', function () {
    Http::fake([
        'api.cohere.com/*' => Http::response([
            'message' => ['content' => [['type' => 'text', 'text' => 'Hello from Cohere']]],
            'usage' => [],
            'id' => 'cmd-r-plus',
        ]),
    ]);

    $result = llmNode()->handle(makeLlmInput([
        'operation' => 'generate',
        'provider' => 'cohere',
        'prompt' => 'Say hello',
    ], ['api_key' => 'test-key']));

    expect($result->status)->toBe(ExecutionNodeStatus::Completed)
        ->and($result->output['response'])->toBe('Hello from Cohere');
});

// --- Azure ---

test('azure generate returns response text', function () {
    Http::fake([
        'my-azure.openai.azure.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello from Azure']]],
            'usage' => [],
        ]),
    ]);

    $result = llmNode()->handle(makeLlmInput([
        'operation' => 'generate',
        'provider' => 'azure',
        'prompt' => 'Say hello',
    ], [
        'api_key' => 'test-key',
        'endpoint' => 'https://my-azure.openai.azure.com',
        'deployment' => 'gpt-4o',
        'api_version' => '2025-04-01-preview',
    ]));

    expect($result->status)->toBe(ExecutionNodeStatus::Completed)
        ->and($result->output['response'])->toBe('Hello from Azure');
});

// --- OpenAI-compatible providers ---

test('groq generate returns response text', function () {
    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello from Groq']]],
            'usage' => [],
            'model' => 'llama-3.3-70b-versatile',
        ]),
    ]);

    $result = llmNode()->handle(makeLlmInput([
        'operation' => 'generate',
        'provider' => 'groq',
        'prompt' => 'Say hello',
    ], ['api_key' => 'test-key']));

    expect($result->status)->toBe(ExecutionNodeStatus::Completed)
        ->and($result->output['response'])->toBe('Hello from Groq');
});

test('mistral generate returns response text', function () {
    Http::fake([
        'api.mistral.ai/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello from Mistral']]],
            'usage' => [],
            'model' => 'mistral-small-latest',
        ]),
    ]);

    $result = llmNode()->handle(makeLlmInput([
        'operation' => 'generate',
        'provider' => 'mistral',
        'prompt' => 'Say hello',
    ], ['api_key' => 'test-key']));

    expect($result->status)->toBe(ExecutionNodeStatus::Completed)
        ->and($result->output['response'])->toBe('Hello from Mistral');
});

test('deepseek generate returns response text', function () {
    Http::fake([
        'api.deepseek.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello from DeepSeek']]],
            'usage' => [],
            'model' => 'deepseek-chat',
        ]),
    ]);

    $result = llmNode()->handle(makeLlmInput([
        'operation' => 'generate',
        'provider' => 'deepseek',
        'prompt' => 'Say hello',
    ], ['api_key' => 'test-key']));

    expect($result->status)->toBe(ExecutionNodeStatus::Completed)
        ->and($result->output['response'])->toBe('Hello from DeepSeek');
});

test('xai generate returns response text', function () {
    Http::fake([
        'api.x.ai/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello from xAI']]],
            'usage' => [],
            'model' => 'grok-3-mini',
        ]),
    ]);

    $result = llmNode()->handle(makeLlmInput([
        'operation' => 'generate',
        'provider' => 'xai',
        'prompt' => 'Say hello',
    ], ['api_key' => 'test-key']));

    expect($result->status)->toBe(ExecutionNodeStatus::Completed)
        ->and($result->output['response'])->toBe('Hello from xAI');
});

test('openrouter generate returns response text', function () {
    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello from OpenRouter']]],
            'usage' => [],
            'model' => 'openai/gpt-4o-mini',
        ]),
    ]);

    $result = llmNode()->handle(makeLlmInput([
        'operation' => 'generate',
        'provider' => 'openrouter',
        'prompt' => 'Say hello',
    ], ['api_key' => 'test-key']));

    expect($result->status)->toBe(ExecutionNodeStatus::Completed)
        ->and($result->output['response'])->toBe('Hello from OpenRouter');
});

test('ollama generate uses configured base url', function () {
    Http::fake([
        'http://localhost:11434/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello from Ollama']]],
            'usage' => [],
            'model' => 'llama3.2',
        ]),
    ]);

    $result = llmNode()->handle(makeLlmInput([
        'operation' => 'generate',
        'provider' => 'ollama',
        'prompt' => 'Say hello',
    ], ['url' => 'http://localhost:11434']));

    expect($result->status)->toBe(ExecutionNodeStatus::Completed)
        ->and($result->output['response'])->toBe('Hello from Ollama');
});

// --- Unknown provider ---

test('unknown provider returns failure', function () {
    $result = llmNode()->handle(makeLlmInput([
        'operation' => 'generate',
        'provider' => 'unknown-llm',
        'prompt' => 'Hello',
    ]));

    expect($result->status)->toBe(ExecutionNodeStatus::Failed)
        ->and($result->error['message'])->toContain("unknown provider 'unknown-llm'");
});

// --- Operations ---

test('classify operation sends correct system prompt', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'positive']]],
            'usage' => [],
            'model' => 'gpt-4o-mini',
        ]),
    ]);

    $result = llmNode()->handle(makeLlmInput([
        'operation' => 'classify',
        'provider' => 'openai',
        'prompt' => 'I love this product!',
        'categories' => ['positive', 'negative', 'neutral'],
    ], ['api_key' => 'test-key']));

    expect($result->status)->toBe(ExecutionNodeStatus::Completed)
        ->and($result->output['response'])->toBe('positive');

    Http::assertSent(function ($request) {
        $messages = $request->data()['messages'] ?? [];

        return collect($messages)->contains(fn ($m) => str_contains($m['content'] ?? '', 'positive, negative, neutral'));
    });
});

test('summarize operation injects summarize system prompt', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'A short summary.']]],
            'usage' => [],
            'model' => 'gpt-4o-mini',
        ]),
    ]);

    llmNode()->handle(makeLlmInput([
        'operation' => 'summarize',
        'provider' => 'openai',
        'prompt' => 'Long article...',
    ], ['api_key' => 'test-key']));

    Http::assertSent(function ($request) {
        $messages = $request->data()['messages'] ?? [];

        return collect($messages)->contains(fn ($m) => str_contains($m['content'] ?? '', 'Summarize'));
    });
});
