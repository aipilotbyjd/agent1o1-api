<?php

namespace App\Nodes\AiAutomation;

use App\Contracts\HasIcon;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

/**
 * Gumloop's "Ask AI" node: a provider-agnostic single-turn LLM call routed
 * through `laravel/ai`'s own provider abstraction (see docs/NODES_CATALOG.md's
 * "AI nodes" section). The open-ended one: for a recurring job, the
 * classify, extract, summarize, translate, rewrite, sentiment and score nodes
 * are tuned for it and return structured fields.
 */
class AskAiNode extends AbstractAiNode implements HasIcon
{
    public function type(): string
    {
        return 'ask_ai';
    }

    public function name(): string
    {
        return 'Ask AI';
    }

    public function icon(): string
    {
        return 'ai-chat-02';
    }

    public function description(): string
    {
        return 'Prompts an LLM with a single-turn, provider-agnostic call and returns the reply text.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['prompt'],
            'properties' => [
                'model_catalog_slug' => $this->modelFields()['model_catalog_slug'],
                'instructions' => Field::advanced(Field::textarea('Instructions', 'How the AI should behave (system prompt).', 'You are a helpful assistant.')),
                'prompt' => Field::textarea('Prompt', 'What to ask. Use {{templates}} to include data from earlier steps.'),
                'response_format' => Field::advanced(Field::select('Reply as', ['text' => 'Text', 'json' => 'JSON object'], 'Pick JSON to get the reply as fields later steps can use, such as {{ nodes.ask.json.email }}. Describe the fields you want in the prompt.', 'text')),
                'provider' => $this->modelFields()['provider'],
                'model' => $this->modelFields()['model'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'text' => ['title' => 'Reply', 'description' => 'The AI’s reply as text.'],
                'json' => ['title' => 'Reply as JSON', 'description' => 'The reply as fields. Only when “Reply as” is JSON object.'],
                'usage' => self::usageSchema(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $prompt = $this->requiredText($config, 'prompt', 'The prompt');
        $instructions = $config['instructions'] ?? 'You are a helpful assistant.';

        if (($config['response_format'] ?? 'text') !== 'json') {
            return $this->ask($run, $config, $instructions, $prompt);
        }

        $reply = $this->ask($run, $config, $instructions."\n\nReply with a single JSON object and nothing else: no prose and no code fences.", $prompt);
        $json = self::decodeJson($reply['text']);

        if ($json === null) {
            throw new RuntimeException('The AI did not reply with a JSON object. Describe the fields you want more clearly in the prompt, or switch “Reply as” back to Text.');
        }

        return ['text' => $reply['text'], 'json' => $json, 'usage' => $reply['usage']];
    }
}
