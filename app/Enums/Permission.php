<?php

namespace App\Enums;

enum Permission: string
{
    // Workspace
    case WorkspaceView = 'workspace.view';
    case WorkspaceUpdate = 'workspace.update';
    case WorkspaceDelete = 'workspace.delete';
    case WorkspaceTransferOwnership = 'workspace.transfer-ownership';
    case WorkspaceManageBilling = 'workspace.manage-billing';

    // Members
    case MemberView = 'member.view';
    case MemberInvite = 'member.invite';
    case MemberUpdate = 'member.update';
    case MemberRemove = 'member.remove';

    // Billing gates
    case SubscriptionView = 'subscription.view';
    case SubscriptionManage = 'subscription.manage';
    case InvoiceView = 'invoice.view';

    // Workflows
    case WorkflowView = 'workflow.view';
    case WorkflowCreate = 'workflow.create';
    case WorkflowUpdate = 'workflow.update';
    case WorkflowDelete = 'workflow.delete';
    case WorkflowExecute = 'workflow.execute';
    case WorkflowActivate = 'workflow.activate';

    // Executions
    case ExecutionView = 'execution.view';
    case ExecutionManage = 'execution.manage';

    // Credentials
    case CredentialView = 'credential.view';
    case CredentialManage = 'credential.manage';

    // Variables
    case VariableView = 'variable.view';
    case VariableManage = 'variable.manage';

    // Agents
    case AgentView = 'agent.view';
    case AgentCreate = 'agent.create';
    case AgentUpdate = 'agent.update';
    case AgentDelete = 'agent.delete';
    case AgentRun = 'agent.run';

    /**
     * Permissions granted to every role including Viewer (the read floor).
     *
     * @return list<Permission>
     */
    public static function viewOnly(): array
    {
        return [
            self::WorkspaceView,
            self::MemberView,
            self::WorkflowView,
            self::ExecutionView,
            self::AgentView,
        ];
    }

    /**
     * Permissions added at the Member tier — the delta over Viewer.
     * Add here when a new permission should be available to members but not viewers.
     *
     * @return list<Permission>
     */
    public static function memberGrants(): array
    {
        return [
            self::SubscriptionView,
            self::WorkflowCreate,
            self::WorkflowUpdate,
            self::WorkflowExecute,
            self::CredentialView,
            self::VariableView,
            self::AgentCreate,
            self::AgentUpdate,
            self::AgentRun,
        ];
    }

    /**
     * Permissions added at the Editor tier — the delta over Member.
     * Editors can build and publish workflows but cannot manage workspace membership.
     *
     * @return list<Permission>
     */
    public static function editorGrants(): array
    {
        return [
            self::WorkflowDelete,
            self::WorkflowActivate,
            self::ExecutionManage,
            self::CredentialManage,
            self::VariableManage,
            self::AgentDelete,
        ];
    }

    /**
     * Permissions added at the Admin tier — the delta over Editor.
     * Add here when a new permission should be available to admins but not editors.
     *
     * @return list<Permission>
     */
    public static function adminGrants(): array
    {
        return [
            self::WorkspaceUpdate,
            self::MemberInvite,
            self::MemberUpdate,
            self::MemberRemove,
            self::InvoiceView,
        ];
    }

    /** @return array<string, array<Permission>> */
    public static function grouped(): array
    {
        return [
            'workspace' => [
                self::WorkspaceView,
                self::WorkspaceUpdate,
                self::WorkspaceDelete,
                self::WorkspaceTransferOwnership,
                self::WorkspaceManageBilling,
            ],
            'members' => [
                self::MemberView,
                self::MemberInvite,
                self::MemberUpdate,
                self::MemberRemove,
            ],
            'billing' => [
                self::SubscriptionView,
                self::SubscriptionManage,
                self::InvoiceView,
            ],
            'workflows' => [
                self::WorkflowView,
                self::WorkflowCreate,
                self::WorkflowUpdate,
                self::WorkflowDelete,
                self::WorkflowExecute,
                self::WorkflowActivate,
            ],
            'executions' => [
                self::ExecutionView,
                self::ExecutionManage,
            ],
            'credentials' => [
                self::CredentialView,
                self::CredentialManage,
            ],
            'variables' => [
                self::VariableView,
                self::VariableManage,
            ],
            'agents' => [
                self::AgentView,
                self::AgentCreate,
                self::AgentUpdate,
                self::AgentDelete,
                self::AgentRun,
            ],
        ];
    }
}
