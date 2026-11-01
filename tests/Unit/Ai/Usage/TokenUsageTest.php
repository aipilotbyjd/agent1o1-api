<?php

use App\Ai\Usage\TokenUsage;
use Laravel\Ai\Responses\Data\TextUsage;

it('adds cached tokens back into input and reasoning into output for a pre-upgrade record', function () {
    $usage = TokenUsage::fromArray([
        'prompt_tokens' => 1000,
        'completion_tokens' => 200,
        'cache_read_input_tokens' => 300,
        'cache_write_input_tokens' => 50,
        'reasoning_tokens' => 40,
    ]);

    expect($usage)
        ->inputTokens->toBe(1350)
        ->outputTokens->toBe(240)
        ->and($usage->uncachedInputTokens())->toBe(1000)
        ->and($usage->visibleOutputTokens())->toBe(200);
});

it('reads a 1.x record as reported, without adding cached or reasoning tokens again', function () {
    $usage = TokenUsage::fromArray([
        'input_tokens' => 1350,
        'output_tokens' => 240,
        'cache_read_input_tokens' => 300,
        'cache_write_input_tokens' => 50,
        'reasoning_tokens' => 40,
    ]);

    expect($usage)->inputTokens->toBe(1350)->outputTokens->toBe(240)
        ->and($usage->uncachedInputTokens())->toBe(1000);
});

it('treats the counts a provider did not report as zero', function () {
    $usage = TokenUsage::fromSdk(new TextUsage(100, 20));

    expect($usage->toArray())->toBe([
        'input_tokens' => 100,
        'output_tokens' => 20,
        'cache_read_input_tokens' => 0,
        'cache_write_input_tokens' => 0,
        'reasoning_tokens' => 0,
    ]);
});

it('sends the pre-upgrade keys alongside the new ones for API clients', function () {
    $usage = new TokenUsage(inputTokens: 1350, outputTokens: 240, cacheReadInputTokens: 300, cacheWriteInputTokens: 50, reasoningTokens: 40);

    expect($usage->toArrayWithLegacyKeys())
        ->toMatchArray(['input_tokens' => 1350, 'output_tokens' => 240, 'prompt_tokens' => 1000, 'completion_tokens' => 200]);
});
