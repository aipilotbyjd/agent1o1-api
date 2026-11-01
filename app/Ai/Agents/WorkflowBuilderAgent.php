<?php

namespace App\Ai\Agents;

use App\Ai\InlinedAttachments;
use App\Ai\Tools\WorkflowBuilder\AddNodeTool;
use App\Ai\Tools\WorkflowBuilder\ConnectNodesTool;
use App\Ai\Tools\WorkflowBuilder\DisconnectNodesTool;
use App\Ai\Tools\WorkflowBuilder\DryRunWorkflowTool;
use App\Ai\Tools\WorkflowBuilder\InspectNodeOutputTool;
use App\Ai\Tools\WorkflowBuilder\InspectNodeSchemaTool;
use App\Ai\Tools\WorkflowBuilder\ListAvailableNodesTool;
use App\Ai\Tools\WorkflowBuilder\ListConnectedAccountsTool;
use App\Ai\Tools\WorkflowBuilder\ListWorkflowsTool;
use App\Ai\Tools\WorkflowBuilder\ReadDraftTool;
use App\Ai\Tools\WorkflowBuilder\RemoveNodeTool;
use App\Ai\Tools\WorkflowBuilder\UpdateNodeTool;
use App\Ai\Tools\WorkflowBuilder\ValidateWorkflowTool;
use App\Enums\Workflows\BuilderMessageMode;
use App\Enums\Workflows\BuilderMessageStatus;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;

/**
 * Chat-based workflow authoring: the single highest-leverage piece to get
 * right is this system prompt, which encodes the entire interaction
 * contract (read the draft before changing it, inspect before using an
 * unfamiliar type, validate + dry-run before declaring victory). Every tool
 * operates on `$session->draft_graph`, never a live `Workflow`: promoting
 * the draft happens separately (`WorkflowBuilderSessionController::promote()`).
 *
 * Mirrors `WorkspaceAgent`: history comes from the session's own messages
 * table (not the `RemembersConversations` trait's `agent_conversations`
 * tables), so `$beforeMessageId` excludes the just-persisted user turn the
 * same way `WorkspaceAgent` does. Replies that are still being written or
 * that failed are left out too, neither is something the assistant said.
 * Only the latest `HISTORY_LIMIT` messages are sent: the draft itself is
 * the durable state (read_draft), so a long session doesn't have to grow
 * every turn's prompt without bound.
 *
 * `$actingUser` (who sent the message being answered) is who the draft
 * edits are attributed to; see `EditsDraft`.
 *
 * Run from `ProcessWorkflowBuilderMessageJob`; the step and time limits keep
 * a confused model from looping on tool calls until the job is killed.
 */
#[MaxSteps(80)]
#[Timeout(280)]
class WorkflowBuilderAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    public const int HISTORY_LIMIT = 40;

    public function __construct(
        public readonly WorkflowBuilderSession $session,
        private readonly ?string $beforeMessageId = null,
        public readonly ?User $actingUser = null,
        public readonly BuilderMessageMode $mode = BuilderMessageMode::Build,
    ) {}

    public function instructions(): string
    {
        $nodeCount = count($this->session->currentGraph()['nodes']);

        $role = $this->mode === BuilderMessageMode::Ask
            ? <<<'TEXT'
            You are a workflow-building assistant in Ask mode. Answer the user's question about
            a workflow's draft graph (nodes and edges). This turn you can read the draft, look up
            nodes, and validate or dry-run it, but you cannot change it: you have no editing
            tools, so never say you made, will make, or are making a change.
            TEXT
            : <<<'TEXT'
            You are a workflow-building assistant. You edit a workflow's draft graph (nodes and
            edges) on the user's behalf, using the tools available to you: you never describe
            an edit without actually making it.
            TEXT;

        $task = $this->mode === BuilderMessageMode::Ask
            ? <<<'TEXT'
            Call read_draft before answering anything about the draft, so you describe what is
            really there. Refer to nodes by their labels, and explain how data moves between them
            in plain language. To check whether the workflow would run, use validate_workflow and
            dry_run_workflow: dry_run_workflow simulates the graph without calling any external
            service.

            If the honest answer is a change (a fix, a missing step, a better design) describe
            exactly what you would change, then tell the user to switch to Build mode to have it
            made.

            Keep answers short and concrete: a few sentences or a short list, not an essay.
            TEXT
            : <<<'TEXT'
            Make independent tool calls together in a single step, e.g. inspect every node type
            you need at once, then add the nodes, then connect them, rather than one per step.

            Before adding a node of a type you're not already familiar with in this
            conversation, call inspect_node_schema with that node's "type" so the node's "config"
            matches what it expects: adding a node with missing or mistyped config is rejected
            and you will have to correct it. update_node merges config; to delete a field (such
            as "_loop"), pass its name in remove_fields.

            If an edit is rejected because the user changed the draft, call read_draft and
            redo the edit against the current draft.

            Before telling the user the workflow is ready, call validate_workflow, and use
            dry_run_workflow to confirm the wiring resolves: it simulates the graph without
            calling any external service. Fix anything either one reports.

            Keep replies short: people are building, not reading. After your changes, say in
            one or two sentences what you did and what the user may still need to fill in
            (secrets, IDs only they know, an app with no connected account). Don't ask them to
            connect an account you already set on a node.
            TEXT;

        return <<<TEXT
        {$role}

        The draft is currently titled "{$this->session->title}" and has {$nodeCount} node(s).
        If it has any, call read_draft before relying on it: the user can also edit the
        draft by hand between your turns.

        The user may attach files: a screenshot or sketch of a flow, a spec, sample data.
        Treat them as part of the request: work from what they show, and use sample data to
        pick field names and templates.

        How workflows work: list_available_nodes shows which node types exist, and
        inspect_node_schema shows what one expects. A later node reads an earlier node's
        output through a template like {{ nodes.<key>.<field> }}; inspect_node_output shows
        which fields a node is known to produce.

        When the workflow needs something from the person running it (a topic, an email
        address, a file link), add a workflow_input node for each value, connect it to the
        nodes that use it, and reference it as {{ nodes.<input key>.value }}. Every
        workflow_input becomes a field on the run form; give it a clear "name", the right
        "input_type", and "required": true when the workflow can't work without it. Don't
        use run_code, transform or {{ input.<field> }} to stand in for an input. When the
        workflow produces a result (a summary, a draft, a score), finish with a
        workflow_output node whose "value" references it; the run returns each output's
        value under its "name".

        Credentials never go in a config: a stored secret is referenced with
        {{ secrets.<NAME> }}. A node for an app (Gmail, Slack, ...) signs in with a connected
        account: set its config "credential_id" to an id from list_connected_accounts,
        preferring an account the user named in <referenced>, then that app's default.
        When no account is connected for the app, leave credential_id empty and say which app
        to connect. Every node has a short, unique, descriptive key (e.g.
        "send_confirmation_email"). Edges run in order; a router node has one edge per branch
        with the matching "condition" value, and an edge with the condition "error" handles
        a node that fails.

        Flow-logic nodes shape how a run moves: human_approval pauses for a person, wait
        pauses for an external callback, join_paths waits for parallel branches to meet,
        subflow runs another workflow, and loop runs one per item of a list. Their
        inspect_node_schema result includes how_to_wire: read it before using or explaining
        one, since some of them fail (and so need an "error" edge) instead of branching.
        subflow and loop need a published workflow's id from list_workflows. A single node
        repeats once per item when it has a "_loop" config ({"items_path":
        "nodes.<key>.<list>"}), without a loop node.

        {$task}
        TEXT;
    }

    /**
     * Ask mode gets only the tools that read the draft (nothing that can
     * change it) so "don't edit" is enforced, not just requested.
     *
     * @return iterable<int, Tool>
     */
    public function tools(): iterable
    {
        $read = [
            new ReadDraftTool($this->session),
            new ListAvailableNodesTool($this->session),
            new InspectNodeSchemaTool($this->session),
            new InspectNodeOutputTool($this->session),
            new ListWorkflowsTool($this->session),
            new ListConnectedAccountsTool($this->session, $this->actingUser),
        ];

        $check = [
            new ValidateWorkflowTool($this->session),
            new DryRunWorkflowTool($this->session),
        ];

        if ($this->mode === BuilderMessageMode::Ask) {
            return [...$read, ...$check];
        }

        return [
            ...$read,
            new AddNodeTool($this->session, $this->actingUser),
            new UpdateNodeTool($this->session, $this->actingUser),
            new RemoveNodeTool($this->session, $this->actingUser),
            new ConnectNodesTool($this->session, $this->actingUser),
            new DisconnectNodesTool($this->session, $this->actingUser),
            ...$check,
        ];
    }

    /**
     * @return iterable<int, Message>
     */
    public function messages(): iterable
    {
        return $this->session->messages()
            ->with('attachments')
            ->where('processing_status', BuilderMessageStatus::Completed)
            ->where('content', '!=', '')
            ->when($this->beforeMessageId !== null, fn ($query) => $query->where('id', '!=', $this->beforeMessageId))
            ->latest()
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            // A window cut mid-exchange would open on a reply to a message
            // that is no longer there.
            ->skipUntil(fn ($message) => $message->role === 'user')
            // A past message's files are replayed with it, so the model can
            // still refer back to a file sent earlier in the conversation.
            ->map(function ($message) {
                if ($message->attachments->isEmpty()) {
                    return new Message($message->role, $message->content);
                }

                [$content, $attachments] = InlinedAttachments::split($message->content, $message->attachments);

                return new UserMessage($content, $attachments);
            })
            ->values()
            ->all();
    }
}
