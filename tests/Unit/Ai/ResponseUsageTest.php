<?php

use App\Ai\Usage\ResponseUsage;

it('adds up a turn paused before the upgrade and resumed after it', function () {
    $combined = ResponseUsage::combine(
        ['prompt_tokens' => 100, 'completion_tokens' => 10, 'tool_call_count' => 1, 'provider' => 'openai', 'model' => 'old'],
        ['input_tokens' => 200, 'output_tokens' => 20, 'tool_call_count' => 2, 'provider' => 'openai', 'model' => 'new'],
    );

    expect($combined)->toMatchArray([
        'input_tokens' => 300,
        'output_tokens' => 30,
        'tool_call_count' => 3,
        'model' => 'new',
    ])->not->toHaveKey('prompt_tokens');
});
