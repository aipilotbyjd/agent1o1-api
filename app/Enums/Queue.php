<?php

namespace App\Enums;

/**
 * Typed queue names: see docs/STRUCTURE.md's "Queues & Horizon" table for the
 * job-to-queue-to-supervisor mapping. Jobs declare their queue via
 * `->onQueue(Queue::WorkflowExecute->value)`, never a raw string. Dash-
 * separated (not the dot-namespaced form STRUCTURE.md sketches) to match the
 * convention `config/triggers.php`'s `triggers-event`/`triggers-poll` queue
 * names already established in this codebase.
 */
enum Queue: string
{
    case WorkflowDispatch = 'workflows-dispatch';
    case WorkflowExecute = 'workflows-execute';
    case AiAgent = 'ai-agent';
    case AiSubagent = 'ai-subagent';
    case WorkflowBuilder = 'workflow-builder';
    case TriggersPoll = 'triggers-poll';
    case TriggersEvent = 'triggers-event';
    case Billing = 'billing-webhook';
    case Maintenance = 'system-maintenance';
    case Notification = 'system-notification';
    case Broadcast = 'system-broadcast';
}
