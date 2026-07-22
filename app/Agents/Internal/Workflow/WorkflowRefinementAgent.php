<?php

namespace App\Agents\Internal\Workflow;

use App\Agents\Internal\InternalAgent;
use App\Agents\Tools\Draft\AddNodeTool;
use App\Agents\Tools\Draft\ConnectNodesTool;
use App\Agents\Tools\Draft\DisconnectNodesTool;
use App\Agents\Tools\Draft\ReadDraftWorkflowTool;
use App\Agents\Tools\Draft\RemoveNodeTool;
use App\Agents\Tools\Draft\UpdateNodeTool;
use App\Agents\Tools\InspectNodeSchemaTool;
use App\Agents\Tools\ListAvailableNodesTool;
use App\Models\WorkflowBuilderSession;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Stringable;

#[Temperature(0.3)]
#[MaxSteps(20)]
#[Timeout(300)]
class WorkflowRefinementAgent extends InternalAgent implements Conversational, HasTools
{
    use RemembersConversations;

    public function __construct(private readonly WorkflowBuilderSession $session) {}

    public function instructions(): Stringable|string
    {
        $nodeCount = count($this->session->nodes_draft ?? []);
        $edgeCount = count($this->session->edges_draft ?? []);

        return <<<PROMPT
        You are a workflow automation expert. Help the user build and refine their workflow through conversation.
        You have full memory of everything discussed in this session.

        Current draft: "{$this->session->title}" — {$nodeCount} nodes, {$edgeCount} connections.

        Tools available:
        - read_draft_workflow: see what's in the draft right now
        - add_node: add a new node (always inspect its schema first)
        - remove_node: remove a node (also removes its edges)
        - update_node: change a node's name, config, or position
        - connect_nodes: connect two nodes
        - disconnect_nodes: remove a connection
        - list_available_nodes: discover available node types
        - inspect_node_schema: get required config fields for a node type

        Guidelines:
        - Before multiple changes, call read_draft_workflow to see the current state.
        - Always inspect_node_schema before setting config on a new node type.
        - Position nodes left-to-right at 250px intervals, y=200 for the main flow.
        - Be concise. Users are building, not reading. 1-2 sentences per response max.
        - When done with changes, briefly confirm what you did and what the user can do next.
        - If the user asks you to "undo", restore using the context of what was just changed.

        Error recovery:
        - If a tool returns an error, tell the user clearly what went wrong and offer a fix.
        - If a node type doesn't exist, call list_available_nodes and suggest alternatives.
        PROMPT;
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new ReadDraftWorkflowTool($this->session),
            new AddNodeTool($this->session),
            new RemoveNodeTool($this->session),
            new UpdateNodeTool($this->session),
            new ConnectNodesTool($this->session),
            new DisconnectNodesTool($this->session),
            new ListAvailableNodesTool,
            new InspectNodeSchemaTool,
        ];
    }
}
