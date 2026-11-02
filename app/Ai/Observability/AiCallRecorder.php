<?php

namespace App\Ai\Observability;

use App\Models\Ai\AiCallEvent;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Billing\CreditMeter;
use Illuminate\Support\Str;
use Laravel\Ai\Events\AgentFailedOver;
use Laravel\Ai\Events\AudioFailed;
use Laravel\Ai\Events\AudioGenerated;
use Laravel\Ai\Events\EmbeddingsFailed;
use Laravel\Ai\Events\EmbeddingsGenerated;
use Laravel\Ai\Events\ImageFailed;
use Laravel\Ai\Events\ImageGenerated;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Events\StepFailed;
use Laravel\Ai\Events\TranscriptionFailed;
use Laravel\Ai\Events\TranscriptionGenerated;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\Usage;
use Throwable;

/**
 * Writes one `ai_call_events` row per request sent to an AI provider, fed by
 * Laravel AI's own events, so every agent step, workflow node, judge,
 * embeddings, image, audio and transcription call is on the books the same
 * way whichever feature made it, including the ones that failed.
 *
 * Who the call belongs to comes from `AiCallContext`; what it cost comes
 * from `CreditMeter::providerCost()` at the price version in force. A
 * failure to record is reported and swallowed: bookkeeping must never break
 * the call it describes.
 */
class AiCallRecorder
{
    /**
     * The feature calls from each `App\Ai\Agents\*` namespace count under.
     * A namespace not listed is named after itself (see `featureFor()`).
     *
     * @var array<string, string>
     */
    private const array FEATURES = [
        'Workspace' => 'agent_chat',
        'Builder' => 'workflow_builder',
        'Judges' => 'judges',
        'Assistant' => 'assistant',
        'Drafting' => 'drafting',
        'Intelligence' => 'admin_analyst',
    ];

    public function __construct(
        private readonly AiCallContext $context,
        private readonly CreditMeter $meter,
    ) {}

    /**
     * @return array<class-string, string>
     */
    public static function listeners(): array
    {
        return [
            StepCompleted::class => 'stepCompleted',
            StepFailed::class => 'stepFailed',
            AgentFailedOver::class => 'failedOver',
            EmbeddingsGenerated::class => 'embeddingsGenerated',
            EmbeddingsFailed::class => 'unitFailed',
            ImageGenerated::class => 'imageGenerated',
            ImageFailed::class => 'unitFailed',
            AudioGenerated::class => 'audioGenerated',
            AudioFailed::class => 'unitFailed',
            TranscriptionGenerated::class => 'transcriptionGenerated',
            TranscriptionFailed::class => 'unitFailed',
        ];
    }

    public function stepCompleted(StepCompleted $event): void
    {
        $this->write([
            'kind' => 'text',
            'feature' => $this->featureFor($event->agent),
            'agent_class' => $event->agent::class,
            'invocation_id' => $event->invocationId,
            'step' => $event->stepNumber,
            'provider' => $event->provider->name(),
            'model' => $event->model,
            'status' => AiCallEvent::STATUS_OK,
            'latency_ms' => (int) round($event->time),
        ], $event->response->usage);
    }

    public function stepFailed(StepFailed $event): void
    {
        $this->write([
            'kind' => 'text',
            'feature' => $this->featureFor($event->agent),
            'agent_class' => $event->agent::class,
            'invocation_id' => $event->invocationId,
            'step' => $event->stepNumber,
            'provider' => $event->provider->name(),
            'model' => $event->model,
            'status' => AiCallEvent::STATUS_FAILED,
            'latency_ms' => (int) round($event->time),
            ...$this->error($event->exception),
        ]);
    }

    /**
     * The failed step was already recorded by `stepFailed()`; this marks it
     * as one the SDK recovered from by moving to the next provider.
     */
    public function failedOver(AgentFailedOver $event): void
    {
        try {
            $updated = AiCallEvent::query()
                ->where('invocation_id', $event->invocationId)
                ->where('provider', $event->provider->name())
                ->where('status', AiCallEvent::STATUS_FAILED)
                ->update(['status' => AiCallEvent::STATUS_FAILED_OVER]);
        } catch (Throwable $e) {
            report($e);

            return;
        }

        if ($updated === 0) {
            $this->write([
                'kind' => 'text',
                'feature' => $this->featureFor($event->agent),
                'agent_class' => $event->agent::class,
                'invocation_id' => $event->invocationId,
                'provider' => $event->provider->name(),
                'model' => $event->model,
                'status' => AiCallEvent::STATUS_FAILED_OVER,
                ...$this->error($event->exception),
            ]);
        }
    }

    public function embeddingsGenerated(EmbeddingsGenerated $event): void
    {
        $this->writeUnit('embeddings', $event->invocationId, $event->provider, $event->model, $event->response->usage, null);
    }

    public function imageGenerated(ImageGenerated $event): void
    {
        $this->writeUnit('image', $event->invocationId, $event->provider, $event->model, $event->response->usage, $event->response->images->count());
    }

    public function audioGenerated(AudioGenerated $event): void
    {
        $this->writeUnit('audio', $event->invocationId, $event->provider, $event->model, $event->response->usage, null);
    }

    public function transcriptionGenerated(TranscriptionGenerated $event): void
    {
        $seconds = $event->response->usage->audioSeconds;

        $this->writeUnit('transcription', $event->invocationId, $event->provider, $event->model, $event->response->usage, $seconds === null ? null : (int) ceil($seconds));
    }

    public function unitFailed(EmbeddingsFailed|ImageFailed|AudioFailed|TranscriptionFailed $event): void
    {
        $kind = match (true) {
            $event instanceof EmbeddingsFailed => 'embeddings',
            $event instanceof ImageFailed => 'image',
            $event instanceof AudioFailed => 'audio',
            default => 'transcription',
        };

        $this->write([
            'kind' => $kind,
            'feature' => $this->context->get('feature') ?? $kind,
            'invocation_id' => $event->invocationId,
            'provider' => $event->provider->name(),
            'model' => $event->model,
            'status' => AiCallEvent::STATUS_FAILED,
            ...$this->error($event->exception),
        ]);
    }

    private function writeUnit(string $kind, string $invocationId, Provider $provider, string $model, Usage $usage, ?int $units): void
    {
        $this->write([
            'kind' => $kind,
            'feature' => $this->context->get('feature') ?? $kind,
            'invocation_id' => $invocationId,
            'provider' => $provider->name(),
            'model' => $model,
            'status' => AiCallEvent::STATUS_OK,
            'units' => $units,
        ], $usage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(array $attributes, ?Usage $usage = null): void
    {
        try {
            $tokens = $this->tokens($usage);
            $cost = $usage === null
                ? ['usd' => 0.0, 'status' => ByokProviderRegistrar::isByok($attributes['provider']) ? 'byok' : 'none', 'version_id' => null]
                : $this->meter->providerCost([...$this->usageArray($tokens), 'provider' => $attributes['provider'], 'model' => $attributes['model']]);

            AiCallEvent::query()->create([
                'occurred_at' => now(),
                'workspace_id' => $this->context->get('workspace_id'),
                'user_id' => $this->context->get('user_id'),
                'run_id' => $this->context->get('run_id'),
                'subject_type' => $this->context->get('subject_type'),
                'subject_id' => $this->context->get('subject_id'),
                'trace_id' => $this->context->traceId(),
                'is_byok' => ByokProviderRegistrar::isByok($attributes['provider']),
                ...$tokens,
                ...$attributes,
                'provider_cost_usd' => $cost['usd'],
                'price_status' => $cost['status'],
                'price_version_id' => $cost['version_id'],
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array{input_tokens: int, cache_read_tokens: int, cache_write_tokens: int, output_tokens: int, reasoning_tokens: int}
     */
    private function tokens(?Usage $usage): array
    {
        $values = $usage?->toArray() ?? [];

        return [
            'input_tokens' => (int) ($usage?->inputTokens ?? 0),
            'cache_read_tokens' => (int) ($values['cache_read_input_tokens'] ?? 0),
            'cache_write_tokens' => (int) ($values['cache_write_input_tokens'] ?? 0),
            'output_tokens' => (int) ($usage?->outputTokens ?? 0),
            'reasoning_tokens' => (int) ($values['reasoning_tokens'] ?? 0),
        ];
    }

    /**
     * The token counts in the shape `TokenUsage::fromArray()` reads.
     *
     * @param  array{input_tokens: int, cache_read_tokens: int, cache_write_tokens: int, output_tokens: int, reasoning_tokens: int}  $tokens
     * @return array<string, int>
     */
    private function usageArray(array $tokens): array
    {
        return [
            'input_tokens' => $tokens['input_tokens'],
            'output_tokens' => $tokens['output_tokens'],
            'cache_read_input_tokens' => $tokens['cache_read_tokens'],
            'cache_write_input_tokens' => $tokens['cache_write_tokens'],
            'reasoning_tokens' => $tokens['reasoning_tokens'],
        ];
    }

    /**
     * @return array{error_class: string, error_message: string}
     */
    private function error(Throwable $exception): array
    {
        return [
            'error_class' => $exception::class,
            'error_message' => Str::limit($exception->getMessage(), 490),
        ];
    }

    /**
     * The feature a call counts under: what the caller declared, else a
     * known agent's feature, else the agent's namespace under `App\Ai\Agents`
     * (`Judges` → `judges`), else its class name.
     */
    private function featureFor(object $agent): string
    {
        if (($declared = $this->context->get('feature')) !== null) {
            return $declared;
        }

        if (preg_match('/^App\\\\Ai\\\\Agents\\\\([A-Za-z]+)\\\\/', $agent::class, $matches) === 1) {
            return self::FEATURES[$matches[1]] ?? Str::snake($matches[1]);
        }

        return Str::snake(class_basename($agent));
    }
}
