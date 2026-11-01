<?php

namespace App\Enums\Workspaces;

enum Permission: string
{
    case WorkspaceView = 'workspace.view';
    case WorkspaceUpdate = 'workspace.update';
    case WorkspaceDelete = 'workspace.delete';

    case MemberView = 'member.view';
    case MemberInvite = 'member.invite';
    case MemberUpdateRole = 'member.update-role';
    case MemberRemove = 'member.remove';

    case InvitationView = 'invitation.view';

    case WorkflowView = 'workflow.view';
    case WorkflowManage = 'workflow.manage';
    case WorkflowPublish = 'workflow.publish';
    case WorkflowVersion = 'workflow.version';
    case WorkflowTrigger = 'workflow.trigger';
    case WorkflowBuilderUse = 'workflow.builder.use';

    /**
     * Approve or reject someone else's request to publish, in a workspace
     * that requires review before changes go live.
     */
    case WorkflowApprovePublish = 'workflow.approve-publish';

    case PageView = 'page.view';
    case PageManage = 'page.manage';

    case NodeView = 'node.view';
    case NodeManage = 'node.manage';

    case AgentView = 'agent.view';
    case AgentManage = 'agent.manage';
    case AgentChat = 'agent.chat';
    case AgentSkillManage = 'agent.skill.manage';
    case AgentApprove = 'agent.approve';
    case AgentPolicyManage = 'agent.policy.manage';

    case AssistantUse = 'assistant.use';

    case ConnectorView = 'connector.view';
    case ConnectorManage = 'connector.manage';

    /**
     * The workspace's own AI provider keys (bring your own key). Anyone can
     * see the team keys exist; a member can add keys only they use
     * (personal); only admins can add keys the whole workspace uses (team).
     */
    case AiCredentialView = 'ai-credential.view';
    case AiCredentialUsePersonal = 'ai-credential.use-personal';
    case AiCredentialManage = 'ai-credential.manage';

    case SecretView = 'secret.view';
    case SecretManage = 'secret.manage';

    case TemplateView = 'template.view';
    case TemplateManage = 'template.manage';

    case ArtifactView = 'artifact.view';
    case ArtifactManage = 'artifact.manage';

    case RunView = 'run.view';
    case RunTrigger = 'run.trigger';

    case TriggerView = 'trigger.view';
    case TriggerManage = 'trigger.manage';

    case ApiKeyView = 'api-key.view';
    case ApiKeyManage = 'api-key.manage';

    case BillingView = 'billing.view';
    case BillingManage = 'billing.manage';

    case NotificationChannelView = 'notification-channel.view';
    case NotificationChannelManage = 'notification-channel.manage';

    case AuditLogView = 'audit-log.view';

    /**
     * @return array<int, self>
     */
    public static function viewerGrants(): array
    {
        return [
            self::WorkspaceView,
            self::MemberView,
            self::InvitationView,
            self::WorkflowView,
            self::PageView,
            self::NodeView,
            self::AgentView,
            self::ConnectorView,
            self::AiCredentialView,
            self::SecretView,
            self::ArtifactView,
            self::RunView,
            self::TriggerView,
            self::ApiKeyView,
            self::BillingView,
            self::NotificationChannelView,
            self::TemplateView,
        ];
    }

    /**
     * @return array<int, self>
     */
    public static function memberGrants(): array
    {
        return [
            self::AgentChat,
            self::AssistantUse,
            self::AiCredentialUsePersonal,
            self::WorkflowTrigger,
            self::RunTrigger,
        ];
    }

    /**
     * @return array<int, self>
     */
    public static function editorGrants(): array
    {
        return [
            self::WorkflowManage,
            self::WorkflowPublish,
            self::WorkflowVersion,
            self::WorkflowBuilderUse,
            self::PageManage,
            self::NodeManage,
            self::AgentManage,
            self::AgentSkillManage,
            self::AgentApprove,
            self::ArtifactManage,
            self::TriggerManage,
            self::TemplateManage,
        ];
    }

    /**
     * @return array<int, self>
     */
    public static function adminGrants(): array
    {
        return [
            self::WorkspaceUpdate,
            self::MemberInvite,
            self::MemberUpdateRole,
            self::MemberRemove,
            self::ConnectorManage,
            self::AiCredentialManage,
            self::SecretManage,
            self::ApiKeyManage,
            self::BillingManage,
            self::NotificationChannelManage,
            self::AgentPolicyManage,
            self::AuditLogView,
            self::WorkflowApprovePublish,
        ];
    }

    /**
     * @return array<int, self>
     */
    public static function ownerGrants(): array
    {
        return [
            self::WorkspaceDelete,
        ];
    }
}
