<?php

use App\Enums\Auth\AuthEvent;
use App\Models\Agents\Agent;
use App\Models\Assistant\Assistant;
use App\Models\User;
use App\Notifications\Auth\SecurityAlertNotification;
use App\Services\Agents\SkillInjector;
use App\Services\Assistant\AssistantInstructions;
use App\Services\Assistant\Triggers\TriggerDefinitions;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->member = User::factory()->create(['name' => 'Priya']);
    $this->workspace = app(WorkspaceService::class)->create($this->member, ['name' => 'Acme', 'timezone' => 'Europe/Berlin']);
    $this->member->refresh();
});

describe('saving it', function () {
    it('stores a member timezone under its current name, and clears it back to the workspace\'s', function () {
        Passport::actingAs($this->member);

        $this->patchJson('/api/v1/user', ['timezone' => 'Asia/Calcutta'])
            ->assertSuccessful()
            ->assertJsonPath('data.user.timezone', 'Asia/Kolkata');

        $this->patchJson('/api/v1/user', ['timezone' => null])
            ->assertSuccessful()
            ->assertJsonPath('data.user.timezone', null);
    });

    it('refuses an unknown timezone', function () {
        Passport::actingAs($this->member);

        $this->patchJson('/api/v1/user', ['timezone' => 'Mars/Base'])->assertJsonValidationErrors('timezone');
    });

    it('leaves the timezone alone when only the name changes', function () {
        $this->member->update(['timezone' => 'Asia/Kolkata']);
        Passport::actingAs($this->member);

        $this->patchJson('/api/v1/user', ['name' => 'Priya S'])->assertSuccessful();

        expect($this->member->fresh()->timezone)->toBe('Asia/Kolkata');
    });
});

it('uses the member\'s timezone, else the workspace\'s, else UTC', function () {
    expect($this->member->timezoneIn($this->workspace))->toBe('Europe/Berlin')
        ->and($this->member->timezoneIn(null))->toBe('UTC');

    $this->member->update(['timezone' => 'Asia/Kolkata']);

    expect($this->member->timezoneIn($this->workspace))->toBe('Asia/Kolkata');
});

describe('using it', function () {
    beforeEach(function () {
        $this->member->update(['timezone' => 'Asia/Kolkata']);
        // 20:00 on Saturday in UTC is already Sunday in India.
        $this->travelTo(now()->parse('2026-10-17 20:00:00', 'UTC'));
    });

    it('tells a workspace agent the date on the member\'s calendar', function () {
        $agent = Agent::factory()->forWorkspace($this->workspace)->create();

        expect(app(SkillInjector::class)->instructionsFor($agent, $this->member->id))
            ->toContain('Today is Sunday, October 18, 2026 in Asia/Kolkata.')
            ->and(app(SkillInjector::class)->instructionsFor($agent))
            ->toContain('Today is Saturday, October 17, 2026 in Europe/Berlin.');
    });

    it('tells the assistant the date and timezone of the member it works for', function () {
        $assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->member->id]);

        expect(app(AssistantInstructions::class)->for($assistant))
            ->toContain('Today is Sunday, October 18, 2026 in Asia/Kolkata, Priya\'s timezone.');
    });

    it('starts the assistant\'s triggers in the member\'s timezone', function () {
        $assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->member->id]);

        $trigger = app(TriggerDefinitions::class)->create($assistant, [
            'type' => 'schedule', 'name' => 'Digest', 'prompt' => 'Summarise my inbox.', 'cron' => '0 9 * * *',
        ], 'owner');

        expect($trigger->timezone)->toBe('Asia/Kolkata')
            ->and($trigger->next_run_at->toDateTimeString())->toBe('2026-10-18 03:30:00');
    });

    it('dates a security alert when it happened, in the member\'s timezone', function () {
        $notification = new SecurityAlertNotification(AuthEvent::PasswordChanged);
        $this->travel(2)->hours();

        expect($notification->toMail($this->member)->introLines)
            ->toContain('When: Sun, Oct 18, 2026 1:30 AM Asia/Kolkata');
    });
});
