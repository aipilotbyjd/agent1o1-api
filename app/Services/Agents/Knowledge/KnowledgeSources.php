<?php

namespace App\Services\Agents\Knowledge;

use App\Enums\Agents\KnowledgeSourceStatus;
use App\Enums\Agents\KnowledgeSourceType;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Jobs\Agents\SyncKnowledgeSourceJob;
use App\Models\Agents\KnowledgeSource;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Adding, changing and removing synced knowledge sources. Shared by the
 * knowledge-base API and the assistant's `save_to_knowledge` tool.
 */
class KnowledgeSources
{
    public function __construct(private readonly KnowledgeReaders $readers) {}

    /**
     * @param  array{type?: string, name?: string, collection?: string|null, private?: bool, credential_id?: string|null, config?: array<string, mixed>}  $data
     */
    public function create(Workspace $workspace, User $user, array $data): KnowledgeSource
    {
        $type = KnowledgeSourceType::tryFrom((string) ($data['type'] ?? ''))
            ?? throw ValidationException::withMessages(['type' => 'Choose a web page or a connected app.']);

        Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'collection' => ['nullable', 'string', 'max:100'],
            'credential_id' => ['nullable', 'string'],
            'config' => ['nullable', 'array'],
            ...$this->readers->for($type)->configRules(),
        ])->validate();

        if (KnowledgeSource::query()->where('workspace_id', $workspace->id)->count() >= (int) config('knowledge_base.sources.max_per_workspace')) {
            throw ValidationException::withMessages(['type' => 'This workspace has reached its limit of synced sources.']);
        }

        $private = (bool) ($data['private'] ?? false);
        $credential = $type->connector() === null ? null : $this->credential($workspace, $user, $type->connector(), $data['credential_id'] ?? null);

        $source = KnowledgeSource::query()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $private ? $user->id : null,
            'created_by' => $user->id,
            'collection' => filled($data['collection'] ?? null) ? $data['collection'] : ($private ? 'personal' : 'default'),
            'type' => $type,
            'name' => $data['name'],
            'config' => $data['config'] ?? [],
            'connector_credential_id' => $credential?->id,
        ]);

        $this->queueSync($source);

        return $source;
    }

    /**
     * @param  array{name?: string, config?: array<string, mixed>}  $data
     */
    public function update(KnowledgeSource $source, array $data): KnowledgeSource
    {
        $rules = ['name' => ['sometimes', 'string', 'max:120']];

        if (array_key_exists('config', $data)) {
            $rules = [...$rules, ...$this->readers->for($source->type)->configRules()];
        }

        Validator::make($data, $rules)->validate();

        $source->fill(array_intersect_key($data, array_flip(['name', 'config'])));
        $configChanged = $source->isDirty('config');
        $source->save();

        if ($configChanged) {
            // A different folder, label or page: start again from scratch.
            $source->chunks()->delete();
            $source->forceFill(['sync_cursor' => null])->save();
            $this->queueSync($source);
        }

        return $source;
    }

    public function queueSync(KnowledgeSource $source): void
    {
        $source->forceFill(['status' => KnowledgeSourceStatus::Pending])->save();

        SyncKnowledgeSourceJob::dispatch($source);
    }

    /**
     * The account to read with: the one asked for, else the member's own
     * default for that app, else the workspace's shared one. A member can
     * only pick their own personal accounts or shared ones.
     */
    public function credential(Workspace $workspace, User $user, string $connector, ?string $credentialId): ConnectorCredential
    {
        $candidates = $this->accounts($workspace, $user, $connector);

        $chosen = $credentialId !== null
            ? $candidates->firstWhere('id', $credentialId)
            : $candidates->first();

        return $chosen ?? throw ValidationException::withMessages(['credential_id' => 'Connect this app in Apps first.']);
    }

    /**
     * The accounts `$user` may read an app with: their own personal ones
     * first (the default one leading), then the workspace's shared ones.
     *
     * @return Collection<int, ConnectorCredential>
     */
    public function accounts(Workspace $workspace, User $user, string $connector): Collection
    {
        return $this->usableBy($workspace, $user, $connector)
            ->filter(fn (ConnectorCredential $credential): bool => $credential->isUsable())
            ->sortByDesc(fn (ConnectorCredential $credential): array => [
                $credential->scope === ConnectorCredentialScope::Personal,
                (bool) $credential->is_default,
                $credential->created_at?->getTimestamp() ?? 0,
            ])
            ->values();
    }

    public function hasExpiredAccount(Workspace $workspace, User $user, string $connector): bool
    {
        return $this->usableBy($workspace, $user, $connector)->contains(fn (ConnectorCredential $credential): bool => ! $credential->isUsable());
    }

    /**
     * @return Collection<int, ConnectorCredential>
     */
    private function usableBy(Workspace $workspace, User $user, string $connector): Collection
    {
        return ConnectorCredential::query()
            ->with('connector')
            ->where('workspace_id', $workspace->id)
            ->whereHas('connector', fn ($query) => $query->where('key', $connector))
            ->where(fn ($query) => $query
                ->where('scope', ConnectorCredentialScope::Team->value)
                ->orWhere(fn ($query) => $query->where('scope', ConnectorCredentialScope::Personal->value)->where('created_by', $user->id)))
            ->get();
    }
}
