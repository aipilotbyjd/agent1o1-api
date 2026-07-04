<?php

use App\Enums\Permission;
use App\Enums\Role;

// ── Role permission invariants ────────────────────────────────────────────────

test('every permission is granted to owner', function () {
    $ownerPermissions = Role::Owner->permissions();

    foreach (Permission::cases() as $permission) {
        expect(in_array($permission, $ownerPermissions, true))
            ->toBeTrue("Owner is missing permission: {$permission->value}");
    }
});

test('role permissions are strictly nested Viewer subset Member subset Admin subset Owner', function () {
    $viewer = Role::Viewer->permissions();
    $member = Role::Member->permissions();
    $admin = Role::Admin->permissions();
    $owner = Role::Owner->permissions();

    foreach ($viewer as $perm) {
        expect(in_array($perm, $member, true))->toBeTrue("Member missing Viewer permission: {$perm->value}");
        expect(in_array($perm, $admin, true))->toBeTrue("Admin missing Viewer permission: {$perm->value}");
        expect(in_array($perm, $owner, true))->toBeTrue("Owner missing Viewer permission: {$perm->value}");
    }

    foreach ($member as $perm) {
        expect(in_array($perm, $admin, true))->toBeTrue("Admin missing Member permission: {$perm->value}");
        expect(in_array($perm, $owner, true))->toBeTrue("Owner missing Member permission: {$perm->value}");
    }

    foreach ($admin as $perm) {
        expect(in_array($perm, $owner, true))->toBeTrue("Owner missing Admin permission: {$perm->value}");
    }
});

test('every permission appears in at least one role', function () {
    $all = array_merge(
        Role::Owner->permissions(),
        Role::Admin->permissions(),
        Role::Member->permissions(),
        Role::Viewer->permissions(),
    );

    foreach (Permission::cases() as $permission) {
        expect(in_array($permission, $all, true))
            ->toBeTrue("Permission {$permission->value} is not in any role");
    }
});

test('owner cannot be assigned via invitation', function () {
    expect(Role::assignableValues())->not->toContain(Role::Owner->value);
});

// ── Permission classification drift guard ─────────────────────────────────────
// This test fails CI when a new Permission case is added without deciding
// whether it should be delegated to a lower role or remain owner-only.
// To fix a failure: either add the permission to a lower role in Role::permissions()
// OR add it to the $ownerOnly list below.

test('every permission is explicitly delegated to a lower role or declared owner-only', function () {
    $delegated = array_unique(array_merge(
        Role::Viewer->permissions(),
        Role::Member->permissions(),
        Role::Admin->permissions(),
    ), SORT_REGULAR);

    // Permissions that are deliberately owner-only (not granted to Admin/Member/Viewer).
    // Update this list whenever a new owner-only permission is added to the enum.
    $ownerOnly = [
        Permission::WorkspaceDelete,
        Permission::WorkspaceTransferOwnership,
        Permission::WorkspaceManageBilling,
        Permission::SubscriptionManage,
    ];

    foreach ($ownerOnly as $p) {
        expect(in_array($p, $delegated, true))
            ->toBeFalse("{$p->value} is listed as owner-only but also appears in a lower role");
    }

    foreach (Permission::cases() as $permission) {
        $isDelegated = in_array($permission, $delegated, true);
        $isOwnerOnly = in_array($permission, $ownerOnly, true);

        expect($isDelegated || $isOwnerOnly)
            ->toBeTrue(
                "Permission {$permission->value} is neither delegated to a lower role nor in the owner-only list. ".
                'Add it to Role::Admin/Member/Viewer->permissions() or to $ownerOnly in this test.'
            );
    }
});
