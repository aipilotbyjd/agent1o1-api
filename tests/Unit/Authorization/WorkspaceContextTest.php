<?php

use App\Authorization\FeatureGates;
use App\Authorization\WorkspaceContext;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\Role;

// ── WorkspaceContext::allows() ────────────────────────────────────────────────

test('allows returns false when no role is set', function () {
    $context = new WorkspaceContext;

    expect($context->allows(Permission::WorkspaceView))->toBeFalse();
});

test('allows returns false when permission is not in the role', function () {
    $context = new WorkspaceContext;
    $context->role = Role::Viewer;

    expect($context->allows(Permission::WorkspaceDelete))->toBeFalse();
    expect($context->allows(Permission::WorkspaceManageBilling))->toBeFalse();
    expect($context->allows(Permission::MemberInvite))->toBeFalse();
});

test('allows returns true when role has permission and no feature gate exists', function () {
    $context = new WorkspaceContext;
    $context->role = Role::Viewer;

    expect($context->allows(Permission::WorkspaceView))->toBeTrue();
    expect($context->allows(Permission::WorkflowView))->toBeTrue();
});

test('allows returns true for every owner permission when plan is null', function () {
    $context = new WorkspaceContext;
    $context->role = Role::Owner;

    // Owner gets all permissions; since FeatureGates::map() is currently empty,
    // every permission passes even without a plan resolved.
    foreach (Permission::cases() as $p) {
        expect($context->allows($p))->toBeTrue("Owner should allow {$p->value}");
    }
});

// ── FeatureGates integrity ────────────────────────────────────────────────────

test('FeatureGates map keys are all valid Permission values', function () {
    foreach (FeatureGates::map() as $permissionValue => $feature) {
        expect(Permission::tryFrom($permissionValue))
            ->not->toBeNull("FeatureGates key '{$permissionValue}' is not a valid Permission value");
    }
});

test('FeatureGates map values are all valid Feature cases', function () {
    foreach (FeatureGates::map() as $permissionValue => $feature) {
        expect($feature)->toBeInstanceOf(Feature::class);
    }
});

test('every FeatureGates entry references a permission that is delegated to at least one non-owner role', function () {
    $nonOwnerPerms = Role::Admin->permissions();

    foreach (FeatureGates::map() as $permissionValue => $feature) {
        $p = Permission::from($permissionValue);
        expect(in_array($p, $nonOwnerPerms, true))
            ->toBeTrue(
                "FeatureGates maps '{$permissionValue}' but it is an owner-only permission — ".
                'a gate on an owner-only permission is unreachable for non-owners'
            );
    }
});
