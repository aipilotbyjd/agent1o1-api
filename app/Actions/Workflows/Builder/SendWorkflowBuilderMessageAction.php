<?php

namespace App\Actions\Workflows\Builder;

use App\Actions\Artifacts\StoreArtifactAction;
use App\Enums\Workflows\BuilderMessageMode;
use App\Enums\Workflows\BuilderMessageStatus;
use App\Exceptions\WorkflowBuilderConflictException;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderMessage;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Billing\CreditGate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records the user's message and a pending assistant message, then hands the
 * turn to `ProcessWorkflowBuilderMessageJob`: a turn can run many tool calls
 * and take minutes, far longer than a request should be held open. The
 * client follows the reply on the session's broadcast channel
 * (`WorkflowBuilderActivity`) or by polling the returned message.
 *
 * One turn at a time per session: a second message while a reply is still
 * being written would have two agents editing the same draft from two
 * different conversation histories.
 */
class SendWorkflowBuilderMessageAction
{
    public function __construct(
        private readonly CreditGate $creditGate,
        private readonly StoreArtifactAction $storeArtifact,
    ) {}

    /**
     * `$sender` is who the turn's edits are attributed to: a session is
     * shared across the workspace, so it isn't always the session's owner.
     *
     * @param  array<int, UploadedFile>  $attachments  Files the member attached to this message.
     * @param  BuilderMessageMode  $mode  `Ask` answers without editing the draft.
     * @param  array{nodes?: list<string>, credentials?: list<string>}|null  $context  Steps and accounts the message referenced.
     * @param  string|null  $modelCatalogId  The catalog model the member picked for this turn; null uses the assistant's default.
     * @return WorkflowBuilderMessage The pending assistant message the reply will be written to.
     *
     * @throws WorkflowBuilderConflictException
     */
    public function execute(WorkflowBuilderSession $session, string $message, ?User $sender = null, array $attachments = [], BuilderMessageMode $mode = BuilderMessageMode::Build, ?array $context = null, ?string $modelCatalogId = null): WorkflowBuilderMessage
    {
        $session->assertEditable();
        $this->creditGate->assertCanStartRun($session->workspace);

        [$userMessage, $assistantMessage] = DB::transaction(function () use ($session, $message, $sender, $attachments, $mode, $context, $modelCatalogId): array {
            // Serializes concurrent sends on this session, so two requests
            // can't both see "no reply in flight" and start two turns.
            WorkflowBuilderSession::query()->whereKey($session->id)->lockForUpdate()->first();

            $session->failStaleReplies();

            if ($session->hasReplyInFlight()) {
                throw WorkflowBuilderConflictException::replyInProgress();
            }

            $userMessage = $session->messages()->create([
                'role' => 'user',
                'mode' => $mode,
                'context' => $context,
                'model_catalog_id' => $modelCatalogId,
                'user_id' => $sender?->id,
                'content' => $message,
            ]);

            $this->storeAttachments($session, $userMessage, $attachments, $sender);

            $assistantMessage = $session->messages()->create([
                'role' => 'assistant',
                'mode' => $mode,
                'content' => '',
                'processing_status' => BuilderMessageStatus::Pending,
            ]);

            $session->forceFill(['last_activity_at' => now()])->save();

            return [$userMessage, $assistantMessage];
        });

        ProcessWorkflowBuilderMessageJob::dispatch($session->id, $userMessage->id, $assistantMessage->id);

        return $assistantMessage->fresh();
    }

    /**
     * Stores each attached file as an `Artifact` on `$userMessage`, the same
     * way an agent chat does: every file starts its own artifact group and
     * none is indexed into an agent's knowledge (`searchable: false`).
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function storeAttachments(WorkflowBuilderSession $session, WorkflowBuilderMessage $userMessage, array $files, ?User $sender): void
    {
        foreach ($files as $file) {
            $this->storeArtifact->execute(
                workspace: $session->workspace,
                filename: $file->getClientOriginalName(),
                mimeType: $file->getMimeType() ?? 'application/octet-stream',
                contents: $file,
                createdBy: $sender?->id,
                groupId: (string) Str::uuid(),
                searchable: false,
                builderMessage: $userMessage,
            );
        }
    }
}
