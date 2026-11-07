<?php

use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Notifications\Auth\ConfirmEmailChangeNotification;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Passport\Passport;

it('stores an address lowercased and trimmed', function () {
    $user = User::factory()->create(['email' => '  Jane@Example.COM ']);

    expect($user->fresh()->email)->toBe('jane@example.com');
});

it('registers with the address lowercased', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'Jane@Example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertCreated()->assertJsonPath('data.user.email', 'jane@example.com');
});

it('refuses a second account for the same address in another case', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Again',
        'email' => 'JANE@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    expect(User::query()->count())->toBe(1);
});

it('signs in whatever case the address is typed in', function () {
    User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'Jane@EXAMPLE.com',
        'password' => 'Password1!',
    ])->assertOk()->assertJsonPath('data.user.email', 'jane@example.com');
});

it('treats a change to the same address in another case as no change', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user, 'api')
        ->patchJson('/api/v1/user', ['email' => 'JANE@example.com'])
        ->assertOk()
        ->assertJsonPath('data.user.pending_email', null);

    Notification::assertNothingSentTo($user);
    Notification::assertSentOnDemandTimes(ConfirmEmailChangeNotification::class, 0);
});

it('confirms a change requested in mixed case', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user, 'api')
        ->patchJson('/api/v1/user', ['email' => 'Jane.New@Example.com'])
        ->assertOk()
        ->assertJsonPath('data.user.pending_email', 'jane.new@example.com');

    $this->get(URL::temporarySignedRoute('auth.confirm-email-change', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1('jane.new@example.com'),
    ]))->assertRedirect(config('app.frontend_url').'/settings/account?email_change=confirmed');

    expect($user->fresh()->email)->toBe('jane.new@example.com');
});

it('lets an invitee accept an invitation sent in another case', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    Passport::actingAs($owner);

    $invitationId = $this->postJson("/api/v1/workspaces/{$workspace->id}/invitations", [
        'email' => 'New.Member@Example.com',
        'role' => Role::Member->value,
    ])->assertCreated()->json('data.invitation.id');

    $invitee = User::factory()->create(['email' => 'new.member@example.com']);
    Passport::actingAs($invitee);

    $this->postJson(URL::signedRoute('workspaces.invitations.accept', ['invitation' => $invitationId]))
        ->assertOk();

    expect($workspace->members()->where('user_id', $invitee->id)->exists())->toBeTrue();
});

it('knows an existing member when invited in another case', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/invitations", [
        'email' => 'OWNER@example.com',
        'role' => Role::Member->value,
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

it('does not accept an invitation from a GET, which link scanners follow', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    Passport::actingAs($owner);

    $invitationId = $this->postJson("/api/v1/workspaces/{$workspace->id}/invitations", [
        'email' => 'new.member@example.com',
        'role' => Role::Member->value,
    ])->json('data.invitation.id');

    $invitee = User::factory()->create(['email' => 'new.member@example.com']);
    Passport::actingAs($invitee);

    $this->getJson(URL::signedRoute('workspaces.invitations.accept', ['invitation' => $invitationId]))
        ->assertMethodNotAllowed();

    expect($workspace->members()->where('user_id', $invitee->id)->exists())->toBeFalse();
});
