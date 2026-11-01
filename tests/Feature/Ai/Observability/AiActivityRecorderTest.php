<?php

use App\Ai\Agents\EvalJudgeAgent;
use App\Ai\Observability\AiActivityCard;
use App\Ai\Observability\AiActivityRecorder;
use App\Ai\Tools\SubmitVerdictTool;
use Laravel\Ai\AiManager;
use Laravel\Ai\Events\ProviderFailedOver;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Events\ToolFailed;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Pulse\Facades\Pulse;
use Livewire\Livewire;

beforeEach(function () {
    Pulse::startRecording();

    $this->recorder = new AiActivityRecorder;
    $this->provider = app(AiManager::class)->textProvider('anthropic');
});

function completedStep(object $provider, int $milliseconds, int $inputTokens, int $cacheReads): StepCompleted
{
    return new StepCompleted(
        'inv-1', 0, new EvalJudgeAgent, $provider, 'claude-x', true,
        new StepResponse('', [], FinishReason::Stop, new TextUsage($inputTokens, 10, $cacheReads), new Meta('anthropic', 'claude-x')),
        $milliseconds,
    );
}

it('shows each model\'s steps, step time and how much of its input the prompt cache served', function () {
    $this->recorder->record(completedStep($this->provider, 1200, 1000, 0));
    $this->recorder->record(completedStep($this->provider, 800, 1000, 600));
    $this->recorder->record(new ProviderFailedOver($this->provider, 'claude-x', new ProviderConnectionException('down')));
    Pulse::ingest();

    Livewire::withoutLazyLoading()->test(AiActivityCard::class)
        ->assertSeeInOrder(['anthropic:claude-x', '2', '1,000 ms', '1,200 ms', '30%', '1']);
});

it('shows each tool\'s calls, time and failures', function () {
    $tool = new SubmitVerdictTool;
    $this->recorder->record(new ToolInvoked('inv-1', 't1', new EvalJudgeAgent, $tool, [], 'ok', 40));
    $this->recorder->record(new ToolInvoked('inv-1', 't2', new EvalJudgeAgent, $tool, [], 'ok', 60));
    $this->recorder->record(new ToolFailed('inv-1', 't3', new EvalJudgeAgent, $tool, [], new RuntimeException('boom'), 10));
    Pulse::ingest();

    Livewire::withoutLazyLoading()->test(AiActivityCard::class, ['show' => 'tools'])
        ->assertSeeInOrder([SubmitVerdictTool::NAME, '2', '50 ms', '60 ms', '1']);
});
