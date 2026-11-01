<?php

namespace App\Notifications\Workspace;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Agents\AgentEvalRun;
use App\Models\Agents\AgentSession;
use App\Models\Agents\AgentSessionEvaluation;
use App\Models\Agents\ReflectionRun;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class RunFailedNotification extends WorkspaceEventNotification
{
    private const MAX_ERROR_LENGTH = 300;

    public function __construct(Workspace $workspace, Run $run)
    {
        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::RunFailed,
            title: self::titleFor($run),
            body: self::bodyFor($run->error),
            data: [
                'run_id' => $run->id,
                'workflow_id' => $run->workflow_id,
                'error' => $run->error,
            ],
        );
    }

    /**
     * Names what actually failed, since a run can be a workflow execution or
     * one of several kinds of agent work.
     */
    public static function titleFor(Run $run): string
    {
        $run->loadMissing('runnable');

        return match (true) {
            $run->runnable instanceof Workflow => "Workflow “{$run->runnable->name}” failed",
            $run->runnable instanceof AgentSession => "“{$run->runnable->agent?->name}” couldn't reply",
            $run->runnable instanceof ReflectionRun => "Reflection for “{$run->runnable->agent?->name}” failed",
            $run->runnable instanceof AgentSessionEvaluation => "Grading a chat with “{$run->runnable->agent?->name}” failed",
            $run->runnable instanceof AgentEvalRun => "Eval suite “{$run->runnable->suite?->name}” failed",
            default => self::genericTitleFor($run),
        };
    }

    /**
     * When what ran has since been deleted, its kind is still on the run.
     */
    private static function genericTitleFor(Run $run): string
    {
        return match (Relation::getMorphedModel((string) $run->runnable_type) ?? $run->runnable_type) {
            Workflow::class => 'A workflow run failed',
            AgentSession::class => "An agent couldn't reply",
            ReflectionRun::class => 'An agent reflection failed',
            AgentSessionEvaluation::class => 'Grading an agent chat failed',
            AgentEvalRun::class => 'An eval suite run failed',
            default => 'A run failed',
        };
    }

    public static function bodyFor(?string $error): string
    {
        return $error !== null ? Str::limit($error, self::MAX_ERROR_LENGTH) : 'No error details were recorded.';
    }
}
