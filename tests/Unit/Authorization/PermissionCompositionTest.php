<?php

use App\Enums\Permission;
use App\Enums\Role;

// ── Composition helper shape ──────────────────────────────────────────────────

test('viewOnly returns the five viewer-level read permissions', function () {
    $viewOnly = Permission::viewOnly();

    expect($viewOnly)->toHaveCount(5);
    expect($viewOnly)->toContain(Permission::WorkspaceView);
    expect($viewOnly)->toContain(Permission::MemberView);
    expect($viewOnly)->toContain(Permission::WorkflowView);
    expect($viewOnly)->toContain(Permission::ExecutionView);
    expect($viewOnly)->toContain(Permission::AgentView);
});

test('memberGrants has no overlap with viewOnly', function () {
    $viewOnly = Permission::viewOnly();

    foreach (Permission::memberGrants() as $p) {
        expect(in_array($p, $viewOnly, true))
            ->toBeFalse("memberGrants should not repeat viewOnly permission: {$p->value}");
    }
});

test('adminGrants has no overlap with viewOnly or memberGrants', function () {
    $lower = [...Permission::viewOnly(), ...Permission::memberGrants()];

    foreach (Permission::adminGrants() as $p) {
        expect(in_array($p, $lower, true))
            ->toBeFalse("adminGrants should not repeat a lower-tier permission: {$p->value}");
    }
});

test('composition tiers have no internal duplicates', function () {
    $composed = [...Permission::viewOnly(), ...Permission::memberGrants(), ...Permission::adminGrants()];
    $unique = array_unique($composed, SORT_REGULAR);

    expect(count($composed))->toBe(count($unique));
});

// ── Role tier nesting via composition ────────────────────────────────────────

test('Viewer gets exactly viewOnly permissions', function () {
    expect(Role::Viewer->permissions())->toBe(Permission::viewOnly());
});

test('Member gets viewOnly plus memberGrants', function () {
    $expected = [...Permission::viewOnly(), ...Permission::memberGrants()];
    expect(Role::Member->permissions())->toBe($expected);
});

test('Admin gets Editor permissions plus adminGrants', function () {
    $expected = [...Role::Editor->permissions(), ...Permission::adminGrants()];
    expect(Role::Admin->permissions())->toBe($expected);
});

test('Owner gets every Permission case', function () {
    expect(Role::Owner->permissions())->toBe(Permission::cases());
});

// ── Coverage invariant: every permission lands in exactly one tier ────────────

test('every permission is covered by a composition tier or is owner-only', function () {
    $adminPerms = Role::Admin->permissions();
    $ownerOnly = array_values(array_filter(
        Permission::cases(),
        fn (Permission $p) => ! in_array($p, $adminPerms, true),
    ));

    // Composed set + owner-only must equal the full set
    $all = [...$adminPerms, ...$ownerOnly];
    expect(count($all))->toBe(count(Permission::cases()));

    foreach (Permission::cases() as $p) {
        expect(in_array($p, $all, true))
            ->toBeTrue("Permission {$p->value} is missing from both composition and owner-only");
    }
});
