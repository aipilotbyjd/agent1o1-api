<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\SuperAdmin\SuperAdminAuditLog;

function superAdminAuditEntry(string $action, array $attributes = []): SuperAdminAuditLog
{
    return SuperAdminAuditLog::query()->create([
        'action' => $action,
        'actor_label' => 'system',
        ...$attributes,
    ]);
}

it('lists the audit log newest first, to any role', function (SuperAdminRole $role) {
    superAdminAuditEntry('accounts.created', ['subject_type' => 'super_admin']);
    $this->travel(1)->minutes();
    superAdminAuditEntry('accounts.disabled', ['subject_type' => 'super_admin']);

    $this->withHeaders(superAdminHeaders(superAdmin($role)))
        ->getJson(saUrl('audit-log'))
        ->assertOk()
        ->assertJsonPath('data.0.action', 'accounts.disabled')
        ->assertJsonPath('data.1.action', 'accounts.created');
})->with(array_map(fn (SuperAdminRole $role) => [$role], SuperAdminRole::cases()));

it('filters by action prefix, subject and actor', function () {
    $admin = superAdmin();
    superAdminAuditEntry('accounts.created', ['subject_type' => 'super_admin', 'subject_id' => $admin->id]);
    superAdminAuditEntry('billing.credits.granted', ['subject_type' => 'workspace', 'super_admin_id' => $admin->id]);

    $headers = superAdminHeaders($admin);

    $this->withHeaders($headers)->getJson(saUrl('audit-log?action=accounts.'))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'accounts.created');

    $this->withHeaders($headers)->getJson(saUrl('audit-log?subject_type=workspace'))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'billing.credits.granted');

    $this->withHeaders($headers)->getJson(saUrl("audit-log?actor={$admin->id}"))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.actor.email', $admin->email);
});

it('pages through the log with a cursor', function () {
    foreach (range(1, 3) as $number) {
        superAdminAuditEntry("test.entry.{$number}");
    }

    $headers = superAdminHeaders(superAdmin());

    $first = $this->withHeaders($headers)->getJson(saUrl('audit-log?per_page=2'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.has_more', true);

    $this->withHeaders($headers)->getJson(saUrl('audit-log?per_page=2&cursor='.$first->json('meta.next_cursor')))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.has_more', false);
});

it('shows one entry, with the reason and the before and after', function () {
    $entry = superAdminAuditEntry('accounts.role_changed', [
        'before' => ['role' => 'support'],
        'after' => ['role' => 'finance'],
        'reason' => 'Promoted to cover billing',
    ]);

    $this->withHeaders(superAdminHeaders(superAdmin()))
        ->getJson(saUrl("audit-log/{$entry->id}"))
        ->assertOk()
        ->assertJsonPath('data.reason', 'Promoted to cover billing')
        ->assertJsonPath('data.before.role', 'support')
        ->assertJsonPath('data.after.role', 'finance');
});

it('has no way to change or delete an entry', function () {
    $entry = superAdminAuditEntry('accounts.created');
    $headers = superAdminHeaders(superAdmin());

    $this->withHeaders($headers)->patchJson(saUrl("audit-log/{$entry->id}"), ['action' => 'tampered'])->assertStatus(405);
    $this->withHeaders($headers)->deleteJson(saUrl("audit-log/{$entry->id}"))->assertStatus(405);

    expect($entry->fresh()->action)->toBe('accounts.created');
});
