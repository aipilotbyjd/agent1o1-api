<?php

namespace App\Enums\Agents;

enum ActionToolKind: string
{
    /** A connector or built-in node attached through `agent_node`. */
    case Node = 'node';

    /** A workflow attached through `agent_workflow`. */
    case Workflow = 'workflow';

    /** One of the agent's own built-in tools, e.g. editing its instructions. */
    case Builtin = 'builtin';
}
