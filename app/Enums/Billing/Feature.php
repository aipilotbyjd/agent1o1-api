<?php

namespace App\Enums\Billing;

/**
 * Keys looked up against `Plan.features` (a plain `[key => bool]` JSON map)
 * via `Plan::hasFeature()`.
 */
enum Feature: string
{
    case CreditPacks = 'credit_packs';
    case CreditOverage = 'credit_overage';
    case GitSync = 'git_sync';
    case WorkflowApprovals = 'workflow_approvals';
    case CustomNodes = 'custom_nodes';
    case PrioritySupport = 'priority_support';

    /**
     * Workflow pages anyone with the link can use, not just workspace members.
     */
    case PublicPages = 'public_pages';

    /**
     * The personal assistant's Smart Inbox (labels new mail, drafts replies).
     */
    case SmartInbox = 'smart_inbox';

    /**
     * The assistant's cloud computer for running code.
     */
    case AssistantSandbox = 'assistant_sandbox';

    /**
     * Texting the assistant by SMS.
     */
    case AssistantSms = 'assistant_sms';
}
