<?php

namespace App\Ai\Agents;

use App\Ai\Tools\WorkflowBuilder\SubmitNodeSuggestionsTool;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\ToolChoice;

/**
 * Suggests the next few nodes for a partial draft: the "what could come
 * next?" helper under the builder canvas. The draft and node catalog are in
 * the prompt (`WorkflowBuilderAssistant`); answers only through
 * `SubmitNodeSuggestionsTool`.
 */
#[ToolChoice(ToolChoice::tool, SubmitNodeSuggestionsTool::NAME)]
#[MaxSteps(1)]
class WorkflowNodeSuggestionAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You help people build workflow automations. Given a partial workflow and the catalog of node
        types, suggest the 3 to 5 most useful nodes to add next, most useful first.

        Work out what the workflow is for from its nodes and the user's note, if any, and suggest what
        it is most obviously missing: the next step of the main path, handling for a step that can
        fail, or a way to deliver the result. Only suggest types from the catalog. Don't suggest
        something the workflow already does.

        Submit the suggestions by calling the submit_node_suggestions tool.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [new SubmitNodeSuggestionsTool];
    }
}
