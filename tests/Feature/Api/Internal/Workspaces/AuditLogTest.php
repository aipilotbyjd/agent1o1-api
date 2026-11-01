<?php

use App\Enums\Workspaces\AuditAction;
use App\Enums\Workspaces\Role;
use App\Models\Auth\ApiKey;
use App\Models\Notifications\NotificationChannel;
use App\Models\Secrets\Secret;
use App\Models\User;
use App\Models\Workspaces\AuditLog;
use App\Services\Workspaces\AuditLogger;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->url = "/api/v1/workspaces/{$this->workspace->id}";

    Passport::actingAs($this->owner);
});

function auditEntries($workspace, AuditAction $action)
{
    return AuditLog::query()->where('workspace_id', $workspace->id)->where('action', $action->value)->get();
}

function addMember($workspace, Role $role): User
{
    $user = User::factory()->create();
    $workspace->members()->create(['user_id' => $user->id, 'role' => $role, 'joined_at' => now()]);

    return $user;
}

describe('recording', function () {
    it('records who changed a members role, from what to what', function () {
        $member = addMember($this->workspace, Role::Member);

        $this->patchJson("{$this->url}/members/{$member->id}", ['role' => 'admin'])->assertSuccessful();

        $entry = auditEntries($this->workspace, AuditAction::MemberRoleChanged)->sole();

        expect($entry->actor_id)->toBe($this->owner->id)
            ->and($entry->actor_label)->toBe($this->owner->email)
            ->and($entry->subject_type)->toBe('WorkspaceMember')
            ->and($entry->metadata)->toMatchArray(['user_id' => $member->id, 'from' => 'member', 'to' => 'admin'])
            ->and($entry->ip_address)->not->toBeNull();
    });

    it('records a member being removed', function () {
        $member = addMember($this->workspace, Role::Editor);

        $this->deleteJson("{$this->url}/members/{$member->id}")->assertSuccessful();

        expect(auditEntries($this->workspace, AuditAction::MemberRemoved)->sole()->metadata)
            ->toMatchArray(['user_id' => $member->id, 'role' => 'editor']);
    });

    it('records a member leaving, which is a bulk delete without the fix', function () {
        $member = addMember($this->workspace, Role::Member);
        Passport::actingAs($member);

        $this->postJson("{$this->url}/leave")->assertSuccessful();

        $entry = auditEntries($this->workspace, AuditAction::MemberRemoved)->sole();
        expect($entry->actor_id)->toBe($member->id);
    });

    it('records invitations being created and revoked', function () {
        $this->postJson("{$this->url}/invitations", ['email' => 'new@example.com', 'role' => 'member'])->assertCreated();

        $created = auditEntries($this->workspace, AuditAction::InvitationCreated)->sole();
        expect($created->metadata)->toMatchArray(['email' => 'new@example.com', 'role' => 'member']);

        $this->deleteJson("{$this->url}/invitations/{$created->subject_id}")->assertSuccessful();
        expect(auditEntries($this->workspace, AuditAction::InvitationRevoked))->toHaveCount(1);
    });

    it('records api keys being created and revoked without the key itself', function () {
        $response = $this->postJson("{$this->url}/api-keys", ['name' => 'CI', 'abilities' => ['runs:read']])->assertCreated();
        $plain = $response->json('data.plain_text_key');

        $created = auditEntries($this->workspace, AuditAction::ApiKeyCreated)->sole();
        expect($created->metadata)->toMatchArray(['name' => 'CI', 'abilities' => ['runs:read']])
            ->and(json_encode($created->metadata))->not->toContain($plain);

        $this->deleteJson("{$this->url}/api-keys/{$created->subject_id}")->assertSuccessful();
        expect(auditEntries($this->workspace, AuditAction::ApiKeyRevoked))->toHaveCount(1);
    });

    it('does not log an api key merely being used', function () {
        $key = $this->workspace->apiKeys()->create(['name' => 'CI', 'hashed_key' => ApiKey::hash('x'), 'abilities' => ['*']]);
        AuditLog::query()->delete();

        $key->update(['last_used_at' => now()]);

        expect(AuditLog::query()->count())->toBe(0);
    });

    it('records secret changes by name and attribute, never by value', function () {
        $this->postJson("{$this->url}/secrets", ['key' => 'STRIPE_KEY', 'value' => 'sk_live_super_secret'])->assertCreated();
        $secret = Secret::query()->where('key', 'STRIPE_KEY')->sole();

        $this->patchJson("{$this->url}/secrets/{$secret->id}", ['value' => 'sk_live_rotated_secret'])->assertSuccessful();
        $this->deleteJson("{$this->url}/secrets/{$secret->id}")->assertSuccessful();

        $all = AuditLog::query()->where('workspace_id', $this->workspace->id)->get();

        expect(auditEntries($this->workspace, AuditAction::SecretCreated)->sole()->metadata)->toBe(['key' => 'STRIPE_KEY'])
            ->and(auditEntries($this->workspace, AuditAction::SecretUpdated)->sole()->metadata)->toBe(['key' => 'STRIPE_KEY', 'changed' => ['value']])
            ->and(auditEntries($this->workspace, AuditAction::SecretDeleted))->toHaveCount(1)
            ->and($all->toJson())->not->toContain('sk_live');
    });

    it('does not log a secret merely being read by a run', function () {
        $secret = Secret::factory()->create(['workspace_id' => $this->workspace->id]);
        AuditLog::query()->delete();

        $secret->update(['last_used_at' => now()]);

        expect(AuditLog::query()->count())->toBe(0);
    });

    it('records notification channel changes without the webhook url or signing secret', function () {
        $this->postJson("{$this->url}/notification-channels", [
            'type' => 'webhook', 'name' => 'Ops', 'config' => ['url' => 'https://hooks.example.com/abc', 'signing_secret' => 'whsec_123'],
        ])->assertCreated();
        $channel = NotificationChannel::query()->sole();

        $this->patchJson("{$this->url}/notification-channels/{$channel->id}", ['name' => 'Ops team'])->assertSuccessful();
        $this->deleteJson("{$this->url}/notification-channels/{$channel->id}")->assertSuccessful();

        $all = AuditLog::query()->where('workspace_id', $this->workspace->id)->get();

        expect(auditEntries($this->workspace, AuditAction::NotificationChannelCreated)->sole()->metadata)->toBe(['type' => 'webhook', 'name' => 'Ops'])
            ->and(auditEntries($this->workspace, AuditAction::NotificationChannelUpdated))->toHaveCount(1)
            ->and(auditEntries($this->workspace, AuditAction::NotificationChannelDeleted))->toHaveCount(1)
            ->and($all->toJson())->not->toContain('hooks.example.com')->not->toContain('whsec_123');
    });

    it('records workspace renames, but not the billing columns the system rewrites', function () {
        $this->patchJson($this->url, ['name' => 'Acme Inc'])->assertSuccessful();
        expect(auditEntries($this->workspace, AuditAction::WorkspaceUpdated)->sole()->metadata)->toBe(['changed' => ['name']]);

        AuditLog::query()->delete();
        $this->workspace->fresh()->update(['stripe_id' => 'cus_123']);

        expect(AuditLog::query()->count())->toBe(0);
    });

    it('records agent policy changes', function () {
        $this->putJson("{$this->url}/agent-policy", ['allow_chat_approvals' => true])->assertSuccessful();

        expect(auditEntries($this->workspace, AuditAction::AgentPolicyUpdated)->sole()->metadata['changed'])->toContain('allow_chat_approvals');
    });

    it('attributes activity to the api key on the public api, and to system outside a request', function () {
        $key = $this->workspace->apiKeys()->create(['name' => 'Deploy bot', 'hashed_key' => ApiKey::hash('x'), 'abilities' => ['*']]);
        $logger = app(AuditLogger::class);

        auth()->forgetGuards();
        $system = $logger->record($this->workspace->id, AuditAction::SecretCreated);

        request()->attributes->set('api_key', $key);
        $viaKey = $logger->record($this->workspace->id, AuditAction::SecretCreated);

        expect($system->actor_label)->toBe('system')->and($system->actor_id)->toBeNull()
            ->and($viaKey->actor_label)->toBe('api_key:Deploy bot')->and($viaKey->actor_id)->toBeNull();
    });

    it('does not record anything for models it does not audit', function () {
        $before = AuditLog::query()->count();

        $this->postJson("{$this->url}/agents", ['name' => 'Helper'])->assertCreated();

        expect(AuditLog::query()->count())->toBe($before);
    });
});

describe('listing', function () {
    it('lets an admin read the log, newest first, and filter it', function () {
        $admin = addMember($this->workspace, Role::Admin);
        AuditLog::query()->delete();
        AuditLog::factory()->forWorkspace($this->workspace)->create(['action' => AuditAction::SecretCreated, 'created_at' => now()->subHour()]);
        AuditLog::factory()->forWorkspace($this->workspace)->by($this->owner)->create(['action' => AuditAction::ApiKeyCreated, 'created_at' => now()]);
        Passport::actingAs($admin);

        $this->getJson("{$this->url}/audit-logs")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'api_key.created')
            ->assertJsonPath('data.1.action', 'secret.created')
            ->assertJsonPath('meta.total', 2);

        $this->getJson("{$this->url}/audit-logs?action=secret.created")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("{$this->url}/audit-logs?actor_id={$this->owner->id}")->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.actor', $this->owner->email);
    });

    it('is only readable by admins and owners', function (Role $role, int $status) {
        $user = addMember($this->workspace, $role);
        Passport::actingAs($user);

        $this->getJson("{$this->url}/audit-logs")->assertStatus($status);
    })->with([
        'admin' => [Role::Admin, 200],
        'editor' => [Role::Editor, 403],
        'member' => [Role::Member, 403],
        'viewer' => [Role::Viewer, 403],
    ]);

    it('never shows another workspaces entries', function () {
        $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Globex']);
        AuditLog::query()->where('workspace_id', $this->workspace->id)->delete();
        AuditLog::factory()->forWorkspace($other)->create();

        $this->getJson("{$this->url}/audit-logs")->assertOk()->assertJsonPath('meta.total', 0);
    });

    it('refuses a non-member outright', function () {
        Passport::actingAs(User::factory()->create());

        $this->getJson("{$this->url}/audit-logs")->assertForbidden();
    });

    it('rejects an unknown action filter', function () {
        $this->getJson("{$this->url}/audit-logs?action=nope")->assertUnprocessable();
    });
});
