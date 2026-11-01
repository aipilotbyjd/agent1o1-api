<?php

use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralRewardRule;
use App\Services\Referrals\ReferralCodes;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('creates the user\'s code on first visit and returns their share link', function () {
    $program = ReferralProgram::factory()->asDefault()->create();
    ReferralRewardRule::factory()->forProgram($program)->milestone(3)->credits(5000)->create();
    $user = referralUser(['name' => 'Jane Doe']);

    Passport::actingAs($user);

    $response = $this->getJson('/api/v1/referrals/me')->assertSuccessful();

    $code = $user->referralCode()->first();

    expect($code->code)->toStartWith('jane-doe-');
    $response->assertJsonPath('data.code', $code->code)
        ->assertJsonPath('data.share_url', rtrim(config('app.frontend_url'), '/').'/?ref='.$code->code)
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.reward_workspace.id', $user->current_workspace_id)
        ->assertJsonPath('data.next_milestone.count', 3)
        ->assertJsonPath('data.next_milestone.remaining', 3);
});

it('lets a user customise their code once', function () {
    ReferralProgram::factory()->asDefault()->create();
    Passport::actingAs($user = referralUser());

    $this->patchJson('/api/v1/referrals/me', ['code' => 'Jane'])
        ->assertSuccessful()
        ->assertJsonPath('data.code', 'jane')
        ->assertJsonPath('data.can_customize_code', false);

    $this->patchJson('/api/v1/referrals/me', ['code' => 'jane2'])->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('refuses a code already taken or badly formed', function (string $code) {
    ReferralProgram::factory()->asDefault()->create();
    app(ReferralCodes::class)->forUser(referralUser())->update(['code' => 'taken']);
    Passport::actingAs(referralUser());

    $this->patchJson('/api/v1/referrals/me', ['code' => $code])->assertUnprocessable()->assertJsonValidationErrors('code');
})->with(['taken', 'TAKEN', 'no spaces', '-leading', 'ab']);

it('only sends rewards to a workspace the user owns', function () {
    ReferralProgram::factory()->asDefault()->create();
    $user = referralUser();
    $someoneElses = referralUser()->currentWorkspace;
    $own = app(WorkspaceService::class)->create($user, ['name' => 'Second']);

    Passport::actingAs($user);

    $this->patchJson('/api/v1/referrals/me', ['reward_workspace_id' => $someoneElses->id])->assertUnprocessable();
    $this->patchJson('/api/v1/referrals/me', ['reward_workspace_id' => $own->id])
        ->assertSuccessful()
        ->assertJsonPath('data.reward_workspace.id', $own->id);
});

it('describes the program\'s current terms', function () {
    $program = ReferralProgram::factory()->asDefault()->create(['name' => 'Launch']);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(500)->create();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::Activated)->credits(1000)->create(['is_active' => false]);

    Passport::actingAs(referralUser());

    $this->getJson('/api/v1/referrals/program')
        ->assertSuccessful()
        ->assertJsonPath('data.program.name', 'Launch')
        ->assertJsonCount(1, 'data.terms')
        ->assertJsonPath('data.terms.0.description', 'Your friend gets 500 bonus credits when they sign up and verify their email.');
});

it('reports the program as off when there is none', function () {
    Passport::actingAs(referralUser());

    $this->getJson('/api/v1/referrals/program')->assertSuccessful()->assertJsonPath('data.enabled', false);
});

it('lists referrals with masked emails and the user\'s own rewards', function () {
    $program = ReferralProgram::factory()->asDefault()->create(['require_verified_email' => false]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified)->credits(300)->create();
    $referrer = referralUser();
    referUser($referrer, referralUser(['email' => 'jonathan@acme.test']), $program);

    Passport::actingAs($referrer);

    $this->getJson('/api/v1/referrals')
        ->assertSuccessful()
        ->assertJsonPath('data.0.email', 'j***@acme.test')
        ->assertJsonMissingPath('data.0.rejection_reason');

    $this->getJson('/api/v1/referrals/rewards')
        ->assertSuccessful()
        ->assertJsonPath('data.0.credits', 300)
        ->assertJsonPath('data.0.summary', '300 bonus credits');

    $this->getJson('/api/v1/referrals/stats')
        ->assertSuccessful()
        ->assertJsonPath('data.signups', 1)
        ->assertJsonPath('data.verified', 1)
        ->assertJsonPath('data.credits_earned', 300);
});

it('requires authentication for everything but recording a visit', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/v1/referrals/me'],
    ['PATCH', '/api/v1/referrals/me'],
    ['GET', '/api/v1/referrals/program'],
    ['GET', '/api/v1/referrals/stats'],
    ['GET', '/api/v1/referrals'],
    ['GET', '/api/v1/referrals/rewards'],
    ['POST', '/api/v1/referrals/claim'],
]);
