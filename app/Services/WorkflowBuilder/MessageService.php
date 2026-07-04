<?php

namespace App\Services\WorkflowBuilder;

use App\Enums\BuilderMessageRole;
use App\Jobs\ProcessBuilderMessageJob;
use App\Models\User;
use App\Models\WorkflowBuilderMessage;
use App\Models\WorkflowBuilderSession;

class MessageService
{
    /**
     * Store the user message, dispatch the AI job, return the pending message.
     */
    public function send(WorkflowBuilderSession $session, User $user, string $content): WorkflowBuilderMessage
    {
        $message = $session->messages()->create([
            'role' => BuilderMessageRole::User,
            'content' => $content,
            'processing_status' => 'completed',
        ]);

        $pending = $session->messages()->create([
            'role' => BuilderMessageRole::Assistant,
            'content' => '',
            'processing_status' => 'pending',
        ]);

        ProcessBuilderMessageJob::dispatch($session, $user, $message, $pending);

        $session->update(['last_activity_at' => now()]);

        return $pending;
    }
}
