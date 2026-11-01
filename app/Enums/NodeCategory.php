<?php

namespace App\Enums;

/**
 * Core (built-in, non-integration) node categories: the `Nodes/` folder
 * layout mirrors these 1:1 (docs/STRUCTURE.md's "How node folders tie to
 * NodeCategory"). App/integration categories (`slack`, `github`, ...) are
 * seeded rows only, not enum cases, since they're added per integration.
 */
enum NodeCategory: string
{
    case AiAutomation = 'ai-automation';
    case TriggersEvents = 'triggers-events';
    case InputsOutputs = 'inputs-outputs';
    case FlowLogic = 'flow-logic';
    case DataTransform = 'data-transform';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::AiAutomation => 'AI / Automation',
            self::TriggersEvents => 'Triggers & Events',
            self::InputsOutputs => 'Inputs & Outputs',
            self::FlowLogic => 'Flow Logic',
            self::DataTransform => 'Data Transform',
            self::Custom => 'Your Custom Nodes',
        };
    }
}
