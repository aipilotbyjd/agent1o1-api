<?php

use App\Enums\Workspaces\Role;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantMessage;
use App\Models\Assistant\AssistantSession;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->url = "/api/v1/workspaces/{$this->workspace->id}/assistant/sessions";

    Passport::actingAs($this->owner);
});

it('creates a web session', function () {
    $this->postJson($this->url, ['title' => 'Plan my week'])
        ->assertCreated()
        ->assertJsonPath('data.session.title', 'Plan my week')
        ->assertJsonPath('data.session.origin', 'web')
        ->assertJsonPath('data.session.incognito', false)
        ->assertJsonPath('data.session.expires_at', null);
});

it('expires incognito sessions after a day', function () {
    $this->freezeSecond();

    $this->postJson($this->url, ['incognito' => true])->assertCreated();

    expect(AssistantSession::query()->sole())
        ->incognito->toBeTrue()
        ->expires_at->toEqual(now()->addDay());
});

it('lists only the member own live sessions', function () {
    $mine = AssistantSession::factory()->create(['assistant_id' => $this->assistant->id]);
    AssistantSession::factory()->expired()->create(['assistant_id' => $this->assistant->id]);
    AssistantSession::factory()->create();

    $this->getJson($this->url)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.sessions')
        ->assertJsonPath('data.sessions.0.id', $mine->id);
});

it('hides another member session behind a 404', function () {
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    $theirs = AssistantSession::factory()->create([
        'assistant_id' => Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $member->id])->id,
    ]);

    $this->getJson("{$this->url}/{$theirs->id}")->assertNotFound();
    $this->patchJson("{$this->url}/{$theirs->id}", ['title' => 'Mine now'])->assertNotFound();
    $this->deleteJson("{$this->url}/{$theirs->id}")->assertNotFound();
    $this->getJson("{$this->url}/{$theirs->id}/messages")->assertNotFound();
});

it('hides an expired incognito session', function () {
    $session = AssistantSession::factory()->expired()->create(['assistant_id' => $this->assistant->id]);

    $this->getJson("{$this->url}/{$session->id}")->assertNotFound();
});

it('renames and archives a session', function () {
    $session = AssistantSession::factory()->create(['assistant_id' => $this->assistant->id]);

    $this->patchJson("{$this->url}/{$session->id}", ['title' => 'Renamed', 'status' => 'archived'])
        ->assertSuccessful()
        ->assertJsonPath('data.session.title', 'Renamed')
        ->assertJsonPath('data.session.status', 'archived');
});

it('rejects an unknown status', function () {
    $session = AssistantSession::factory()->create(['assistant_id' => $this->assistant->id]);

    $this->patchJson("{$this->url}/{$session->id}", ['status' => 'deleted'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('deletes a session', function () {
    $session = AssistantSession::factory()->create(['assistant_id' => $this->assistant->id]);

    $this->deleteJson("{$this->url}/{$session->id}")->assertNoContent();

    expect(AssistantSession::query()->find($session->id))->toBeNull();
});

it('pages through messages oldest first', function () {
    $session = AssistantSession::factory()->create(['assistant_id' => $this->assistant->id]);
    $first = AssistantMessage::factory()->create(['assistant_session_id' => $session->id, 'created_at' => now()->subMinute()]);
    AssistantMessage::factory()->fromAssistant()->create(['assistant_session_id' => $session->id]);

    $this->getJson("{$this->url}/{$session->id}/messages")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.1.role', 'assistant');
});
