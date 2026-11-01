<?php

use App\Enums\SuperAdmin\SuperAdminAbility;
use App\Enums\SuperAdmin\SuperAdminRole;

$view = ['stats.view', 'audit.view', 'users.view', 'workspaces.view', 'runs.view', 'billing.view', 'models.view', 'pricing.view', 'broadcasts.view', 'system.view', 'alerts.view'];

it('grants each role exactly the abilities in the matrix', function (SuperAdminRole $role, array $expected) {
    expect($role->abilities())->toEqualCanonicalizing($expected);
})->with([
    'owner holds everything' => [SuperAdminRole::Owner, array_map(fn (SuperAdminAbility $ability) => $ability->value, SuperAdminAbility::cases())],
    'operator' => [SuperAdminRole::Operator, [...$view, 'users.suspend', 'users.force-logout', 'workspaces.suspend', 'customers.annotate', 'runs.retry', 'runs.cancel', 'catalog.manage', 'referrals.manage', 'system.manage', 'models.manage', 'broadcasts.manage', 'alerts.manage']],
    'finance' => [SuperAdminRole::Finance, [...$view, 'referrals.manage', 'customers.annotate', 'billing.grant-credits', 'billing.extend-plan', 'billing.refund', 'billing.replay-webhooks', 'models.prices', 'revenue.view', 'pricing.manage', 'pricing.prices']],
    'support' => [SuperAdminRole::Support, [...$view, 'users.suspend', 'users.force-logout', 'workspaces.suspend', 'customers.annotate', 'runs.retry', 'runs.cancel']],
    'viewer' => [SuperAdminRole::Viewer, $view],
]);

it('lets only owners manage accounts', function (SuperAdminRole $role, int $status) {
    $this->withHeaders(superAdminHeaders(superAdmin($role)))
        ->getJson(saUrl('accounts'))
        ->assertStatus($status);
})->with([
    'owner' => [SuperAdminRole::Owner, 200],
    'operator' => [SuperAdminRole::Operator, 403],
    'finance' => [SuperAdminRole::Finance, 403],
    'support' => [SuperAdminRole::Support, 403],
    'viewer' => [SuperAdminRole::Viewer, 403],
]);

it('lets every role read the audit log', function (SuperAdminRole $role) {
    $this->withHeaders(superAdminHeaders(superAdmin($role)))
        ->getJson(saUrl('audit-log'))
        ->assertOk();
})->with(array_map(fn (SuperAdminRole $role) => [$role], SuperAdminRole::cases()));

it('applies a role change on the very next request', function () {
    $admin = superAdmin(SuperAdminRole::Owner);
    $headers = superAdminHeaders($admin);

    $this->withHeaders($headers)->getJson(saUrl('accounts'))->assertOk();

    $admin->forceFill(['role' => SuperAdminRole::Viewer])->save();

    $this->withHeaders($headers)->getJson(saUrl('accounts'))->assertForbidden();
});

it('reports the role and its abilities on /me', function () {
    $admin = superAdmin(SuperAdminRole::Support);

    $this->withHeaders(superAdminHeaders($admin))->getJson(saUrl('auth/me'))
        ->assertOk()
        ->assertJsonPath('data.admin.role', 'support')
        ->assertJsonFragment(['abilities' => SuperAdminRole::Support->abilities()]);
});
