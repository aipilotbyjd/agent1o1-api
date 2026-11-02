<?php

use App\Ai\Agents\Judges\EvalJudgeAgent;
use App\Ai\Support\ToolSubmission;
use App\Ai\Tools\Submissions\SubmitVerdictTool;
use App\Exceptions\ModelSubmissionException;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\ToolChoice;

it('asks the provider for the result as structured output, with no tools to call', function () {
    EvalJudgeAgent::fake([['passed' => true, 'reason' => 'It declines politely.']]);

    $response = (new EvalJudgeAgent)->prompt('Grade this.');

    expect(ToolSubmission::arguments($response, SubmitVerdictTool::NAME))->toBe(['passed' => true, 'reason' => 'It declines politely.'])
        ->and(iterator_to_array((new EvalJudgeAgent)->tools()))->toBe([])
        ->and((new EvalJudgeAgent)->toolChoice())->toBeNull();
});

it('forces the submit tool instead for an agent listed in ai.structured_output.forced_tool', function () {
    config(['ai.structured_output.forced_tool' => [EvalJudgeAgent::class]]);
    EvalJudgeAgent::fake([new ToolCall('call_1', SubmitVerdictTool::NAME, ['passed' => false, 'reason' => 'It leaks the secret.'])]);

    $response = (new EvalJudgeAgent)->prompt('Grade this.');

    expect(ToolSubmission::arguments($response, SubmitVerdictTool::NAME))->toBe(['passed' => false, 'reason' => 'It leaks the secret.'])
        ->and((new EvalJudgeAgent)->toolChoice())->toEqual(ToolChoice::tool(SubmitVerdictTool::NAME))
        ->and((new EvalJudgeAgent)->schema(new JsonSchemaTypeFactory))->toBe([]);
});

it('refuses a plain text reply in place of the structured result', function () {
    EvalJudgeAgent::fake(['Looks fine to me.']);

    $response = (new EvalJudgeAgent)->prompt('Grade this.');

    expect(fn () => ToolSubmission::arguments($response, SubmitVerdictTool::NAME))
        ->toThrow(ModelSubmissionException::class, 'did not return a structured result');
});
