<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->admin = superAdmin(SuperAdminRole::Support);
    $this->headers = superAdminHeaders($this->admin);
});

/**
 * Signs `$user` in through the real user API and returns their access token.
 */
function signInAs(User $user): string
{
    return test()->flushHeaders()
        ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSuccessful()
        ->json('data.tokens.access_token');
}

it('lists and searches users, to any role', function (SuperAdminRole $role) {
    User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']);
    User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.test']);

    $headers = superAdminHeaders(superAdmin($role));

    $this->withHeaders($headers)->getJson(saUrl('users'))->assertOk()->assertJsonCount(2, 'data');
    $this->withHeaders($headers)->getJson(saUrl('users?q=ada'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'ada@example.test');
})->with(array_map(fn (SuperAdminRole $role) => [$role], SuperAdminRole::cases()));

it('filters users by status and sorts them', function () {
    User::factory()->create(['email' => 'a@example.test', 'suspended_at' => now()]);
    User::factory()->unverified()->create(['email' => 'b@example.test']);
    User::factory()->create(['email' => 'c@example.test']);

    $this->withHeaders($this->headers)->getJson(saUrl('users?status=suspended'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'a@example.test');
    $this->withHeaders($this->headers)->getJson(saUrl('users?status=unverified'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'b@example.test');
    $this->withHeaders($this->headers)->getJson(saUrl('users?status=active'))->assertJsonCount(2, 'data');
    $this->withHeaders($this->headers)->getJson(saUrl('users?sort=email'))->assertJsonPath('data.0.email', 'a@example.test');
    $this->withHeaders($this->headers)->getJson(saUrl('users?sort=password'))->assertUnprocessable();
});

it('shows a user with their workspaces, never their secrets, and records the view', function () {
    $user = User::factory()->create();
    app(WorkspaceService::class)->create($user, ['name' => 'Acme']);

    $this->withHeaders($this->headers)->getJson(saUrl("users/{$user->id}"))
        ->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.workspaces.0.name', 'Acme')
        ->assertJsonPath('data.workspaces.0.role', 'owner')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.two_factor_secret');

    $log = SuperAdminAuditLog::query()->where('action', 'users.viewed')->firstOrFail();

    expect($log->super_admin_id)->toBe($this->admin->id)->and($log->subject_id)->toBe($user->id);
});

it('suspends a user, ends their sessions and keeps them out until restored', function () {
    $user = User::factory()->create();
    $token = signInAs($user);

    $this->withToken($token)->getJson('/api/v1/user')->assertOk();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("users/{$user->id}/suspend"), ['reason' => 'Chargeback fraud, case 4411'])
        ->assertOk()
        ->assertJsonPath('data.suspended_reason', 'Chargeback fraud, case 4411');

    // Their open session is gone, and a new one cannot start. (The test container
    // keeps the Passport guard's last user between requests; a real server does not.)
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/user')->assertUnauthorized();

    $this->flushHeaders()->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'This account has been suspended. Contact support.');

    $this->withHeaders($this->headers)
        ->postJson(saUrl("users/{$user->id}/unsuspend"), ['reason' => 'Dispute resolved in their favour'])
        ->assertOk()
        ->assertJsonPath('data.suspended_at', null);

    signInAs($user);

    expect(SuperAdminAuditLog::query()->whereIn('action', ['users.suspended', 'users.unsuspended'])->count())->toBe(2);
});

it('requires a reason to suspend', function () {
    $user = User::factory()->create();

    $this->withHeaders($this->headers)->postJson(saUrl("users/{$user->id}/suspend"))
        ->assertUnprocessable()->assertJsonValidationErrors('reason');

    expect($user->fresh()->isSuspended())->toBeFalse();
});

it('keeps suspending to the roles that hold the ability', function (SuperAdminRole $role, int $status) {
    $user = User::factory()->create();

    $this->withHeaders(superAdminHeaders(superAdmin($role)))
        ->postJson(saUrl("users/{$user->id}/suspend"), ['reason' => 'Verified abuse report'])
        ->assertStatus($status);
})->with([
    'owner' => [SuperAdminRole::Owner, 200],
    'operator' => [SuperAdminRole::Operator, 200],
    'support' => [SuperAdminRole::Support, 200],
    'finance' => [SuperAdminRole::Finance, 403],
    'viewer' => [SuperAdminRole::Viewer, 403],
]);

it('blocks a suspended user from finishing a two-factor sign-in', function () {
    $user = User::factory()->create();
    $user->forceFill(['suspended_at' => now()])->save();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
});

it('ends every session of a user on request without suspending them', function () {
    $user = User::factory()->create();
    $token = signInAs($user);

    $this->withHeaders($this->headers)
        ->postJson(saUrl("users/{$user->id}/force-logout"), ['reason' => 'Reported lost laptop'])
        ->assertOk();

    $this->withToken($token)->getJson('/api/v1/user')->assertUnauthorized();
    expect($user->fresh()->isSuspended())->toBeFalse();

    signInAs($user);
});

it('does nothing, and audits nothing, when the user is already in that state', function () {
    $user = User::factory()->create();

    $this->withHeaders($this->headers)->postJson(saUrl("users/{$user->id}/unsuspend"), ['reason' => 'Nothing to restore'])->assertOk();

    expect(SuperAdminAuditLog::query()->where('action', 'users.unsuspended')->exists())->toBeFalse();
});

it('does not let a user token reach the user admin routes', function () {
    Passport::actingAs(User::factory()->create());

    $this->flushHeaders()->getJson(saUrl('users'))->assertUnauthorized();
});

it('resends the verification email to an unverified user, and says why', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("users/{$user->id}/resend-verification"), ['reason' => 'User says the email never came'])
        ->assertOk();

    Notification::assertSentTo($user, VerifyEmail::class);

    $log = SuperAdminAuditLog::query()->where('action', 'users.verification_resent')->firstOrFail();

    expect($log->reason)->toBe('User says the email never came')->and($log->subject_id)->toBe($user->id);
});

it('will not resend verification to a verified address, or without a reason', function () {
    Notification::fake();
    $verified = User::factory()->create();
    $unverified = User::factory()->unverified()->create();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("users/{$verified->id}/resend-verification"), ['reason' => 'Checking the address'])
        ->assertStatus(409);

    $this->withHeaders($this->headers)
        ->postJson(saUrl("users/{$unverified->id}/resend-verification"))
        ->assertUnprocessable()->assertJsonValidationErrors('reason');

    Notification::assertNothingSent();
});

it('keeps resending verification to the roles that can act on users', function (SuperAdminRole $role, int $status) {
    $user = User::factory()->unverified()->create();

    $this->withHeaders(superAdminHeaders(superAdmin($role)))
        ->postJson(saUrl("users/{$user->id}/resend-verification"), ['reason' => 'User says the email never came'])
        ->assertStatus($status);
})->with([
    'support' => [SuperAdminRole::Support, 200],
    'operator' => [SuperAdminRole::Operator, 200],
    'finance' => [SuperAdminRole::Finance, 403],
    'viewer' => [SuperAdminRole::Viewer, 403],
]);
