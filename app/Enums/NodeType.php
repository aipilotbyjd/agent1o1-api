<?php

namespace App\Enums;

enum NodeType: string
{
    case Trigger = 'trigger';
    case HttpRequest = 'http_request';
    case Transform = 'transform';
    case Code = 'code';
    case SetVariable = 'set_variable';
    case SubWorkflow = 'sub_workflow';
    case Agent = 'agent';
    case Condition = 'condition';
    case Loop = 'loop';
    case Merge = 'merge';
    case Delay = 'delay';
    case Wait = 'wait';
    case TryCatch = 'try_catch';
    case Retry = 'retry';

    public function isAsync(): bool
    {
        return match ($this) {
            self::HttpRequest, self::Code, self::Agent, self::Delay, self::Wait => true,
            default => false,
        };
    }

    public function isSuspendable(): bool
    {
        return match ($this) {
            self::Delay, self::Wait => true,
            default => false,
        };
    }

    public function isFlowControl(): bool
    {
        return match ($this) {
            self::Condition, self::Loop, self::Merge, self::TryCatch, self::Retry => true,
            default => false,
        };
    }
}
