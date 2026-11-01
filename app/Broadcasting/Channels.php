<?php

namespace App\Broadcasting;

use App\Models\Agents\AgentSession;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSession;
use App\Models\Runs\Run;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\WorkflowPage;

/**
 * The one place channel names are spelled out. Both halves of broadcasting:
 * the events that publish (`Events\Runs\*`, `Events\Agents\*`) and the
 * authorization callbacks that gate subscription (`routes/channels.php`):
 * build their names from here, so a rename can't leave a channel that is
 * published to but never authorized (or vice versa).
 *
 * Everything is workspace-scoped and private: run output and agent replies
 * routinely carry customer data, so there is no public tier. The one presence
 * channel (`WORKFLOW_PRESENCE_PATTERN`) shares only who has a workflow open:
 * a member's id, name and avatar, never content.
 */
final class Channels
{
    /**
     * Routing patterns for `routes/channels.php`. Kept beside the builders
     * below so the authorization side and the publishing side are edited
     * together: Laravel needs a literal pattern with `{...}` placeholders to
     * match against, which is why these can't just be the builders' output.
     */
    public const string WORKSPACE_RUNS_PATTERN = 'workspaces.{workspaceId}.runs';

    public const string RUN_PATTERN = 'workspaces.{workspaceId}.runs.{runId}';

    public const string AGENT_SESSION_PATTERN = 'workspaces.{workspaceId}.agent-sessions.{sessionId}';

    public const string WORKFLOW_BUILDER_SESSION_PATTERN = 'workspaces.{workspaceId}.workflow-builder-sessions.{sessionId}';

    public const string ASSISTANT_SESSION_PATTERN = 'workspaces.{workspaceId}.assistant-sessions.{sessionId}';

    public const string ASSISTANT_PATTERN = 'workspaces.{workspaceId}.assistants.{assistantId}';

    public const string PAGE_PATTERN = 'workspaces.{workspaceId}.pages.{pageId}';

    public const string WORKFLOW_PRESENCE_PATTERN = 'workspaces.{workspaceId}.workflows.{workflowId}';

    /**
     * The one platform-wide channel: every signed-in member may listen. It
     * only ever carries a nudge to refetch announcements (never their text),
     * so a banner aimed at one segment is not leaked to another.
     */
    public const string ANNOUNCEMENTS = 'announcements';

    /**
     * Workspace-wide firehose of run state transitions: what a "Runs" list
     * subscribes to. Individual node steps do *not* land here; they would
     * flood a list view that only renders one row per run.
     */
    public static function workspaceRuns(string $workspaceId): string
    {
        return "workspaces.{$workspaceId}.runs";
    }

    /**
     * One run's own stream: its status transitions plus every node step;
     * what an open run canvas subscribes to.
     */
    public static function run(Run $run): string
    {
        return "workspaces.{$run->workspace_id}.runs.{$run->id}";
    }

    /**
     * One agent conversation's stream: messages as they are appended.
     */
    public static function agentSession(AgentSession $session): string
    {
        return "workspaces.{$session->workspace_id}.agent-sessions.{$session->id}";
    }

    /**
     * One workflow-builder session's stream: the assistant's reply as it is
     * written, its tool calls, and each change it makes to the draft.
     */
    /**
     * One workflow page's stream for the people building it: comments as
     * they change and AI generations as they finish. Never a visitor's.
     */
    public static function page(WorkflowPage $page): string
    {
        return "workspaces.{$page->workspace_id}.pages.{$page->id}";
    }

    public static function workflowBuilderSession(WorkflowBuilderSession $session): string
    {
        return "workspaces.{$session->workspace_id}.workflow-builder-sessions.{$session->id}";
    }

    /**
     * One personal-assistant conversation's stream: the reply as it is
     * written, tool activity, and turn status. Only the assistant's owner
     * may subscribe (`WorkspaceChannelGate::assistantSession()`).
     */
    public static function assistantSession(AssistantSession $session): string
    {
        $workspaceId = $session->assistant->workspace_id;

        return "workspaces.{$workspaceId}.assistant-sessions.{$session->id}";
    }

    /**
     * One personal assistant's own stream: background reports finishing.
     * Owner only (`WorkspaceChannelGate::assistant()`).
     */
    public static function assistant(Assistant $assistant): string
    {
        return "workspaces.{$assistant->workspace_id}.assistants.{$assistant->id}";
    }
}
