<?php

namespace App\Observers;

use App\Enums\Workspaces\AuditAction;
use App\Models\Agents\WorkspaceAgentPolicy;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Models\Ai\WorkspaceModelSettings;
use App\Models\Auth\ApiKey;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Notifications\NotificationChannel;
use App\Models\Secrets\Secret;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceInvitation;
use App\Models\Workspaces\WorkspaceMember;
use App\Services\Workspaces\AuditLogger;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns changes to the security-relevant models into audit log entries, so
 * every route in (the internal API, the public API, a job) is covered by
 * one place instead of a call in each controller.
 *
 * Only model events are seen: a query-builder `->delete()` or `->update()`
 * skips them, so code that must be audited loads and changes models.
 *
 * What is recorded is names, ids and which attributes changed, never a
 * value, since several of these models hold secrets.
 */
class AuditObserver
{
    /**
     * Columns the system rewrites on its own; a change to only these is not
     * something a person did.
     *
     * @var list<string>
     */
    private const array SYSTEM_COLUMNS = ['updated_at', 'last_used_at', 'last_run_at'];

    /**
     * Workspace columns worth auditing: the rest (billing state, credit
     * balances) are rewritten by the system constantly.
     *
     * @var list<string>
     */
    private const array WORKSPACE_COLUMNS = ['name', 'slug', 'avatar', 'owner_id'];

    /**
     * AI provider key columns a person changes: the rest are re-check
     * results and the default flag, which has its own audit entry.
     *
     * @var list<string>
     */
    private const array AI_PROVIDER_CREDENTIAL_COLUMNS = ['name', 'data'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function created(Model $model): void
    {
        $this->write($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->write($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->write($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->write($model, 'restored');
    }

    private function write(Model $model, string $event): void
    {
        $changed = array_values(array_diff(array_keys($model->getChanges()), self::SYSTEM_COLUMNS));

        if ($model instanceof Workspace) {
            $changed = array_values(array_intersect($changed, self::WORKSPACE_COLUMNS));
        }

        if ($model instanceof AiProviderCredential) {
            $changed = array_values(array_intersect($changed, self::AI_PROVIDER_CREDENTIAL_COLUMNS));
        }

        // The policy row is only ever persisted by saving it, never implicitly
        // (`forWorkspace()` hands back an unsaved default), so its first save
        // is a change to the workspace's policy like any later one.
        if (($model instanceof WorkspaceAgentPolicy || $model instanceof WorkspaceAiKeyPolicy || $model instanceof WorkspaceModelSettings) && $event === 'created') {
            $event = 'updated';
            $changed = array_values(array_diff(array_keys($model->getAttributes()), ['id', 'workspace_id', 'created_at', 'updated_at']));
        }

        $action = $this->actionFor($model, $event, $changed);

        if ($action === null) {
            return;
        }

        $workspaceId = $model instanceof Workspace ? $model->id : $model->workspace_id;

        $this->audit->record($workspaceId, $action, $model, $this->metadataFor($model, $event, $changed));
    }

    /**
     * @param  list<string>  $changed
     */
    private function actionFor(Model $model, string $event, array $changed): ?AuditAction
    {
        $updated = $event === 'updated' && $changed !== [];

        return match (true) {
            $model instanceof Workspace => match (true) {
                $updated => AuditAction::WorkspaceUpdated,
                $event === 'deleted' => AuditAction::WorkspaceDeleted,
                default => null,
            },
            $model instanceof WorkspaceMember => match (true) {
                in_array($event, ['created', 'restored'], true) => AuditAction::MemberAdded,
                $event === 'updated' && in_array('role', $changed, true) => AuditAction::MemberRoleChanged,
                $event === 'deleted' => AuditAction::MemberRemoved,
                default => null,
            },
            $model instanceof WorkspaceInvitation => match (true) {
                $event === 'created' => AuditAction::InvitationCreated,
                $event === 'deleted' && $model->accepted_at === null => AuditAction::InvitationRevoked,
                default => null,
            },
            $model instanceof ApiKey => match ($event) {
                'created' => AuditAction::ApiKeyCreated,
                'deleted' => AuditAction::ApiKeyRevoked,
                default => null,
            },
            $model instanceof Secret => match (true) {
                $event === 'created' => AuditAction::SecretCreated,
                $updated => AuditAction::SecretUpdated,
                $event === 'deleted' => AuditAction::SecretDeleted,
                default => null,
            },
            // Updates are skipped on purpose: the token is rewritten on every OAuth refresh.
            $model instanceof ConnectorCredential => match ($event) {
                'created' => AuditAction::ConnectorCredentialCreated,
                'deleted' => AuditAction::ConnectorCredentialDeleted,
                default => null,
            },
            $model instanceof AiProviderCredential => match (true) {
                $event === 'created' => AuditAction::AiProviderCredentialCreated,
                $updated => AuditAction::AiProviderCredentialUpdated,
                $event === 'deleted' => AuditAction::AiProviderCredentialDeleted,
                $event === 'restored' => AuditAction::AiProviderCredentialRestored,
                default => null,
            },
            $model instanceof NotificationChannel => match (true) {
                $event === 'created' => AuditAction::NotificationChannelCreated,
                $updated => AuditAction::NotificationChannelUpdated,
                $event === 'deleted' => AuditAction::NotificationChannelDeleted,
                default => null,
            },
            $model instanceof WorkspaceAgentPolicy => $updated ? AuditAction::AgentPolicyUpdated : null,
            $model instanceof WorkspaceAiKeyPolicy => $updated ? AuditAction::AiKeyPolicyUpdated : null,
            $model instanceof WorkspaceModelSettings => $updated ? AuditAction::ModelSettingsUpdated : null,
            default => null,
        };
    }

    /**
     * @param  list<string>  $changed
     * @return array<string, mixed>
     */
    private function metadataFor(Model $model, string $event, array $changed): array
    {
        $metadata = match (true) {
            $model instanceof WorkspaceMember => [
                'user_id' => $model->user_id,
                'role' => $this->scalar($model->role),
                ...($event === 'updated' ? ['from' => $this->scalar($model->getOriginal('role')), 'to' => $this->scalar($model->role)] : []),
            ],
            $model instanceof WorkspaceInvitation => ['email' => $model->email, 'role' => $this->scalar($model->role)],
            $model instanceof ApiKey => ['name' => $model->name, 'abilities' => $model->abilities],
            $model instanceof Secret => ['key' => $model->key],
            $model instanceof ConnectorCredential => ['name' => $model->name, 'connector_id' => $model->connector_id, 'scope' => $this->scalar($model->scope)],
            $model instanceof AiProviderCredential => ['name' => $model->name, 'execution_provider' => $model->execution_provider, 'scope' => $this->scalar($model->scope)],
            $model instanceof NotificationChannel => ['type' => $model->type, 'name' => $model->name],
            $model instanceof WorkspaceModelSettings => ['default_model_catalog_id' => $model->default_model_catalog_id, 'allowed_model_catalog_ids' => $model->allowed_model_catalog_ids],
            $model instanceof WorkspaceAiKeyPolicy => ['platform_usage' => $this->scalar($model->platform_usage), 'allow_personal_keys' => $model->allow_personal_keys],
            default => [],
        };

        if ($event === 'updated') {
            $metadata['changed'] = $changed;
        }

        return $metadata;
    }

    private function scalar(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
