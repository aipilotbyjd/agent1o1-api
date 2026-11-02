<?php

use App\Ai\Agents\Workspace\AdHocPromptAgent;
use App\Enums\NodeRunStatus;
use App\Models\Runs\NodeRun;
use App\Nodes\AiAutomation\AbstractAiNode;
use App\Services\Workflows\NodeRegistry;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * Runs one AI step in a real workflow against a faked model reply.
 *
 * @param  array<string, mixed>  $config
 * @param  array<string, mixed>  $input
 */
function runAiNode(string $type, array $config, string $reply, array $input = []): NodeRun
{
    AdHocPromptAgent::fake([$reply]);

    return runNodeStep($type, ['provider' => 'openai', 'model' => 'gpt-4o', ...$config], $input);
}

it('registers every AI node', function () {
    foreach (['ask_ai', 'ai_classify', 'ai_extract', 'ai_summarize', 'ai_translate', 'ai_sentiment', 'ai_rewrite', 'ai_score'] as $type) {
        $node = app(NodeRegistry::class)->resolve($type);

        expect($node)->toBeInstanceOf(AbstractAiNode::class)
            ->and($node->type())->toBe($type)
            ->and($node->category())->toBe('ai-automation')
            ->and($node->outputSchema()['properties'])->toHaveKey('usage');
    }
});

it('reads a JSON reply even when the model wraps it in a code fence or preamble', function () {
    expect(AbstractAiNode::decodeJson("```json\n{\"a\": 1}\n```"))->toBe(['a' => 1])
        ->and(AbstractAiNode::decodeJson('Sure! Here you go: {"a": {"b": 2}} Hope that helps.'))->toBe(['a' => ['b' => 2]])
        ->and(AbstractAiNode::decodeJson('[1, 2]'))->toBeNull()
        ->and(AbstractAiNode::decodeJson('not json'))->toBeNull();
});

it('asks for the reply as JSON fields when Ask AI is set to', function () {
    $nodeRun = runAiNode('ask_ai', ['prompt' => 'Give me a name and an age', 'response_format' => 'json'], '{"name": "Ada", "age": 36}');

    expect($nodeRun->status)->toBe(NodeRunStatus::Completed)
        ->and($nodeRun->output['json'])->toBe(['name' => 'Ada', 'age' => 36])
        ->and($nodeRun->output['text'])->toContain('Ada');
});

it('fails Ask AI in JSON mode when the reply is not JSON', function () {
    expect(runAiNode('ask_ai', ['prompt' => 'Hi', 'response_format' => 'json'], 'Sorry, I cannot.')->status)->toBe(NodeRunStatus::Failed);
});

it('classifies into a category and exposes it as the branch', function () {
    $nodeRun = runAiNode('ai_classify', [
        'text' => 'I was charged twice this month',
        'categories' => ['billing', 'bug', 'how-to'],
    ], '{"label": "Billing", "confidence": 0.93, "reason": "Mentions a double charge."}');

    expect($nodeRun->output)->toMatchArray(['label' => 'billing', 'result' => 'billing', 'confidence' => 0.93]);

    AdHocPromptAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'billing, bug, how-to') && str_contains($prompt->prompt, 'charged twice'));
});

it('refuses a category that is not in the list and needs at least two', function () {
    $outside = runAiNode('ai_classify', ['text' => 'hello', 'categories' => ['a', 'b']], '{"label": "c", "confidence": 1}');
    $single = runAiNode('ai_classify', ['text' => 'hello', 'categories' => ['only']], '{"label": "only"}');

    expect($outside->status)->toBe(NodeRunStatus::Failed)
        ->and($single->status)->toBe(NodeRunStatus::Failed);
});

it('allows other only when asked', function () {
    $nodeRun = runAiNode('ai_classify', ['text' => 'hello', 'categories' => ['a', 'b'], 'allow_other' => true], '{"label": "other", "confidence": 0.4}');

    expect($nodeRun->output['label'])->toBe('other');
});

it('extracts each field on its own and names the ones the text lacks', function () {
    $nodeRun = runAiNode('ai_extract', [
        'text' => 'Hi, I am Ada from Initech. Please send the quote.',
        'fields' => ['name' => 'the sender', 'company' => 'their company', 'phone' => 'their phone number'],
    ], '{"name": "Ada", "company": "Initech", "phone": ""}');

    expect($nodeRun->output)->toMatchArray([
        'name' => 'Ada',
        'company' => 'Initech',
        'phone' => null,
        'data' => ['name' => 'Ada', 'company' => 'Initech', 'phone' => null],
        'missing' => ['phone'],
    ]);
});

it('rejects extract field names that clash with its own output or are not safe keys', function () {
    expect(runAiNode('ai_extract', ['text' => 'x', 'fields' => ['usage' => 'the usage']], '{}')->status)->toBe(NodeRunStatus::Failed)
        ->and(runAiNode('ai_extract', ['text' => 'x', 'fields' => ['bad name' => 'x']], '{}')->status)->toBe(NodeRunStatus::Failed)
        ->and(runAiNode('ai_extract', ['text' => 'x', 'fields' => []], '{}')->status)->toBe(NodeRunStatus::Failed);
});

it('summarizes in the chosen style and length', function () {
    $nodeRun = runAiNode('ai_summarize', ['text' => 'A long report.', 'style' => 'executive', 'length' => 'short', 'focus' => 'risks'], "  The summary.  \n");

    expect($nodeRun->output['summary'])->toBe('The summary.');

    AdHocPromptAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->agent->instructions(), 'executive summary')
        && str_contains($prompt->agent->instructions(), '2 to 3 sentences')
        && str_contains($prompt->agent->instructions(), 'risks'));
});

it('translates and reports the language', function () {
    $nodeRun = runAiNode('ai_translate', ['text' => 'Hello {{name}}', 'target_language' => 'Spanish', 'tone' => 'formal'], 'Hola {{name}}');

    expect($nodeRun->output)->toMatchArray(['translation' => 'Hola {{name}}', 'target_language' => 'Spanish']);

    AdHocPromptAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->agent->instructions(), 'into Spanish') && str_contains($prompt->agent->instructions(), 'formal'));
});

it('reads sentiment, clamps the score and falls back to neutral for an unknown value', function () {
    $good = runAiNode('ai_sentiment', ['text' => 'Love it'], '{"sentiment": "Positive", "score": 4, "emotions": ["Delight", "joy", "pride", "extra"], "reason": "Enthusiastic."}');
    $odd = runAiNode('ai_sentiment', ['text' => 'meh'], '{"sentiment": "ecstatic", "score": -9}');

    expect($good->output)->toMatchArray(['sentiment' => 'positive', 'result' => 'positive', 'score' => 1.0, 'emotions' => ['delight', 'joy', 'pride']])
        ->and($odd->output)->toMatchArray(['sentiment' => 'neutral', 'score' => -1.0]);
});

it('rewrites with the chosen goal', function () {
    $nodeRun = runAiNode('ai_rewrite', ['text' => 'ur order is late sorry', 'goal' => 'formal', 'audience' => 'enterprise buyers'], 'Your order is delayed, and we apologise.');

    expect($nodeRun->output['text'])->toBe('Your order is delayed, and we apologise.');

    AdHocPromptAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->agent->instructions(), 'formal tone') && str_contains($prompt->agent->instructions(), 'enterprise buyers'));
});

it('turns a score into a verdict by fixed thresholds, not by what the model says', function (int $score, string $verdict) {
    $nodeRun = runAiNode('ai_score', ['text' => 'A lead', 'criteria' => 'Is a decision maker'], json_encode(['score' => $score, 'strengths' => ['Senior'], 'gaps' => ['No budget']]));

    expect($nodeRun->output)->toMatchArray(['score' => min($score, 10), 'max_score' => 10, 'verdict' => $verdict, 'result' => $verdict, 'strengths' => ['Senior'], 'gaps' => ['No budget']]);
})->with([
    'strong' => [8, 'strong'],
    'edge of strong' => [7, 'strong'],
    'moderate' => [5, 'moderate'],
    'edge of weak' => [4, 'moderate'],
    'weak' => [3, 'weak'],
    'above the scale is capped' => [14, 'strong'],
]);

it('scores on the scale and thresholds the user sets', function () {
    $nodeRun = runAiNode('ai_score', ['text' => 'A lead', 'criteria' => 'x', 'max_score' => 100, 'strong_from' => 90, 'weak_below' => 50], '{"score": 80}');

    expect($nodeRun->output)->toMatchArray(['score' => 80, 'percent' => 80, 'verdict' => 'moderate']);
});

it('refuses empty or oversized text instead of calling the model', function () {
    AdHocPromptAgent::fake(['unused']);

    expect(fn () => executeNode('ai_summarize', ['text' => "   \n"]))->toThrow(InvalidArgumentException::class, 'is empty')
        ->and(fn () => executeNode('ai_summarize', ['text' => str_repeat('a', 100001)]))->toThrow(InvalidArgumentException::class, 'too long')
        ->and(fn () => executeNode('ask_ai', ['prompt' => ' ']))->toThrow(InvalidArgumentException::class, 'is empty');

    AdHocPromptAgent::assertNeverPrompted();
});
