<?php

namespace App\Jobs\Workflows;

use App\Actions\Billing\DeductCreditsAction;
use App\Ai\Agents\WorkflowBuilderAgent;
use App\Ai\Agents\WorkflowBuilderTitleAgent;
use App\Ai\BuilderMessageReferences;
use App\Ai\InlinedAttachments;
use App\Ai\ResponseUsage;
use App\Ai\Tools\WorkflowBuilder\SubmitWorkflowTitleTool;
use App\Ai\ToolSubmission;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Queue;
use App\Enums\Workflows\BuilderMessageStatus;
use App\Events\Workflows\WorkflowBuilderActivity;
use App\Models\Ai\ModelCatalog;
use App\Models\Workflows\Builder\WorkflowBuilderMessage;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Billing\CreditMeter;
use App\Services\Workflows\DraftDiff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\Error as StreamError;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use RuntimeException;
use Throwable;

/**
 * One workflow-builder chat turn: streams `WorkflowBuilderAgent`'s reply,
 * broadcasting each delta, tool call and draft change as it happens
 * (`WorkflowBuilderActivity`), then writes the finished reply (with a
 * summary of what it changed) onto the pending assistant message
 * `SendWorkflowBuilderMessageAction` created, and charges the turn.
 *
 * Never retried: the tools have already edited the draft by the time a turn
 * fails, and replaying the prompt against that half-edited draft would do
 * the work twice. A failed turn is marked failed and left for the user to
 * resend. Failed turns aren't charged, matching `RecordRunCreditUsage`.
 */
class ProcessWorkflowBuilderMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const string FAILURE_MESSAGE = "The assistant couldn't finish this reply. Any changes it already made to the draft are kept: send your message again to continue.";

    /**
     * Appended when the agent used its whole step budget while still calling
     * tools, so a half-built draft isn't presented as a finished one.
     */
    public const string OUT_OF_STEPS_NOTE = "I ran out of steps before finishing. Everything I've built so far is on the canvas: say \"continue\" and I'll pick up where I left off.";

    /**
     * The `model_catalog` slug for the workflow-builder assistant itself
     * (not a user-selectable entry: see `ModelCatalog::is_internal`).
     * Gumloop's own equivalent internal agent runs on an open-weight model
     * via Fireworks for a large cost saving with no UX change; this lets you
     * do the same here by adding/enabling a route on this slug.
     */
    public const string MODEL_CATALOG_SLUG = 'workflow-builder-assistant';

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public string $sessionId,
        public string $userMessageId,
        public string $assistantMessageId,
    ) {
        $this->onQueue(Queue::WorkflowBuilder->value);
    }

    public function handle(ModelCatalogResolver $modelCatalog, ByokProviderRegistrar $byok, CreditMeter $meter, DeductCreditsAction $deductCredits): void
    {
        $session = WorkflowBuilderSession::find($this->sessionId);
        $userMessage = WorkflowBuilderMessage::find($this->userMessageId);
        $reply = WorkflowBuilderMessage::find($this->assistantMessageId);

        if ($session === null || $userMessage === null || $reply?->processing_status !== BuilderMessageStatus::Pending) {
            return;
        }

        $reply->update(['processing_status' => BuilderMessageStatus::Processing]);
        $this->broadcast($session, 'status', ['status' => BuilderMessageStatus::Processing->value]);

        $graphBefore = $session->currentGraph();
        $lockVersionBefore = $session->draft_lock_version;

        ['provider' => $provider, 'model' => $usedModel] = $this->provider($modelCatalog, $byok, $session, $userMessage);

        // The model that answers is not always the one picked: a pick with no
        // working route falls back to the assistant's own, so what ran is
        // recorded on the reply, for the member to see.
        $reply->update(['model_catalog_id' => $usedModel?->id]);

        try {
            $response = $this->streamTurn($session, $userMessage, $provider);
            $titleUsage = $this->autoTitle($session, $userMessage, $provider);
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($session, $reply, $graphBefore);

            return;
        }

        $session->refresh();

        $reply->update([
            'content' => $response['cut_off']
                ? trim($response['text']."\n\n".self::OUT_OF_STEPS_NOTE)
                : $response['text'],
            'processing_status' => BuilderMessageStatus::Completed,
            'actions' => DraftDiff::between($graphBefore, $session->currentGraph()),
            'draft_version_id' => $session->draft_lock_version > $lockVersionBefore
                ? $session->draftVersions()->latest()->latest('id')->value('id')
                : null,
        ]);

        $session->forceFill(['last_activity_at' => now()])->save();

        $deductCredits->execute(
            $session->workspace,
            CreditTransactionType::WorkflowBuilder,
            $reply->id,
            $meter->costForWorkflowBuilder($response['usage']) + ($titleUsage !== null ? $meter->costForWorkflowBuilder($titleUsage) : 0),
            'Workflow builder',
            allowOverdraft: true,
        );

        $this->broadcast($session, 'status', [
            'status' => BuilderMessageStatus::Completed->value,
            'title' => $session->title,
            'draft_lock_version' => $session->draft_lock_version,
        ]);
    }

    /**
     * Reached when the worker itself gave up on the job (timeout, crash):
     * `handle()` catches everything that happens inside the turn.
     */
    public function failed(?Throwable $exception = null): void
    {
        $session = WorkflowBuilderSession::find($this->sessionId);
        $reply = WorkflowBuilderMessage::find($this->assistantMessageId);

        if ($session !== null && $reply !== null && $reply->processing_status?->isInFlight()) {
            $this->markFailed($session, $reply, null);
        }
    }

    /**
     * @return array{text: string, usage: array<string, mixed>, cut_off: bool}
     */
    /**
     * @param  array<string, string>|null  $provider
     */
    private function streamTurn(WorkflowBuilderSession $session, WorkflowBuilderMessage $userMessage, ?array $provider): array
    {
        $startedAt = now();
        $finished = null;
        $endReason = null;
        $lastBroadcastLockVersion = $session->draft_lock_version;

        [$prompt, $attachments] = InlinedAttachments::split($userMessage->content, $userMessage->attachments);
        $prompt = collect([$prompt, BuilderMessageReferences::describe($userMessage)])->filter()->implode("\n\n");

        $stream = (new WorkflowBuilderAgent($session, $userMessage->id, $userMessage->user, $userMessage->mode))
            ->stream($prompt, $attachments, provider: $provider);

        $stream->then(function (StreamedAgentResponse $response) use (&$finished): void {
            $finished = $response;
        });

        foreach ($stream as $event) {
            match (true) {
                $event instanceof TextDelta => $this->broadcast($session, 'delta', ['delta' => $event->delta]),
                $event instanceof ToolCall => $this->broadcast($session, 'tool-call', [
                    'id' => $event->toolCall->id,
                    'name' => $event->toolCall->name,
                    'arguments' => $event->toolCall->arguments,
                ]),
                $event instanceof ToolResult => $this->broadcast($session, 'tool-result', [
                    'id' => $event->toolResult->id,
                    'name' => $event->toolResult->name,
                    'output' => (string) ($event->error ?? $event->toolResult->result),
                    'successful' => $event->successful,
                ]),
                $event instanceof StreamError && ! $event->recoverable => throw new RuntimeException("Provider stream error: {$event->message}"),
                $event instanceof StreamEnd => $endReason = $event->reason,
                default => null,
            };

            // The tools share this session instance, so a draft edit shows
            // up here as soon as the tool that made it returns.
            if ($event instanceof ToolResult && $session->draft_lock_version !== $lastBroadcastLockVersion) {
                $lastBroadcastLockVersion = $session->draft_lock_version;

                $this->broadcast($session, 'draft', [
                    'draft_lock_version' => $session->draft_lock_version,
                    'label' => $session->draftVersions()->latest()->latest('id')->value('label'),
                ]);
            }
        }

        if ($finished === null) {
            throw new RuntimeException('The builder stream ended without a response.');
        }

        return [
            'text' => (string) ($stream->text ?? $finished->text),
            'usage' => ResponseUsage::from($finished, $startedAt),
            // The loop only ends on a tool-call step when MaxSteps ran out.
            'cut_off' => $endReason === FinishReason::ToolCalls->value,
        ];
    }

    /**
     * Names a still-untitled session after its first message. Best-effort:
     * a failure here leaves the default title rather than failing a turn
     * that otherwise succeeded.
     *
     * @return array<string, mixed>|null The title call's usage, to be charged with the turn.
     */
    /**
     * @param  array<string, string>|null  $provider
     */
    private function autoTitle(WorkflowBuilderSession $session, WorkflowBuilderMessage $userMessage, ?array $provider): ?array
    {
        if ($session->title !== WorkflowBuilderSession::DEFAULT_TITLE) {
            return null;
        }

        $startedAt = now();

        try {
            $response = (new WorkflowBuilderTitleAgent)->prompt(
                Str::limit($userMessage->content, 2000),
                provider: $provider,
            );

            $title = trim((string) (ToolSubmission::arguments($response, SubmitWorkflowTitleTool::NAME)['title'] ?? ''));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if ($title !== '') {
            $session->forceFill(['title' => Str::limit($title, 80, '')])->save();
        }

        return ResponseUsage::from($response, $startedAt);
    }

    /**
     * @param  array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}|null  $graphBefore
     */
    private function markFailed(WorkflowBuilderSession $session, WorkflowBuilderMessage $reply, ?array $graphBefore): void
    {
        $session->refresh();

        $reply->update([
            'processing_status' => BuilderMessageStatus::Failed,
            'error_message' => self::FAILURE_MESSAGE,
            'actions' => $graphBefore !== null ? DraftDiff::between($graphBefore, $session->currentGraph()) : $reply->actions,
        ]);

        $this->broadcast($session, 'status', [
            'status' => BuilderMessageStatus::Failed->value,
            'error_message' => self::FAILURE_MESSAGE,
            'draft_lock_version' => $session->draft_lock_version,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function broadcast(WorkflowBuilderSession $session, string $type, array $payload): void
    {
        // A broadcaster outage must not fail a turn whose real output (the
        // message and the draft) is persisted regardless.
        try {
            WorkflowBuilderActivity::dispatch($session, $this->assistantMessageId, $type, $payload);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Runs on the model the member picked for this turn, else on the
     * `workflow-builder-assistant` entry. Falls back to `laravel/ai`'s own
     * default provider (`config('ai.default')`) when neither resolves (the
     * entry hasn't been seeded or has no enabled route), so a fresh install
     * isn't broken by this. Runs on the workspace's own provider key where
     * the member sending the message has one: see `ByokProviderRegistrar`.
     *
     * `model` is the catalog entry that was actually used, which is the one
     * picked only when it resolved; it is null when the default provider
     * answered with no catalog entry behind it.
     *
     * @return array{provider: array<string, string>|null, model: ModelCatalog|null}
     */
    private function provider(ModelCatalogResolver $modelCatalog, ByokProviderRegistrar $byok, WorkflowBuilderSession $session, WorkflowBuilderMessage $userMessage): array
    {
        $picked = $userMessage->modelCatalog;
        $slugs = array_filter([$picked?->slug, self::MODEL_CATALOG_SLUG]);

        foreach ($slugs as $slug) {
            try {
                $chain = $modelCatalog->providerChain($slug);
            } catch (RuntimeException) {
                continue;
            }

            [$provider] = $byok->apply($chain, null, $session->workspace_id, $userMessage->user_id);

            return [
                'provider' => $provider,
                'model' => $picked?->slug === $slug ? $picked : ModelCatalog::query()->where('slug', $slug)->first(),
            ];
        }

        return ['provider' => null, 'model' => null];
    }
}
