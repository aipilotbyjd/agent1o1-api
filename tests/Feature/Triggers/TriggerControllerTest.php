<?php

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerType;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspace(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('creates a webhook trigger, issuing a token', function () {
    [$workspace, $owner] = ownerWorkspace();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers", [
        'target_type' => 'workflow',
        'target_id' => $workflow->id,
        'type' => 'webhook',
    ]);

    $response->assertCreated();
    expect($response->json('data.trigger.token'))->not->toBeNull();
});

it('does not issue a token for a manual trigger', function () {
    [$workspace, $owner] = ownerWorkspace();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers", [
        'target_type' => 'workflow',
        'target_id' => $workflow->id,
        'type' => 'manual',
    ]);

    $response->assertCreated();
    expect($response->json('data.trigger.token'))->toBeNull();
});

it('lets a viewer read triggers but not manage them', function () {
    [$workspace, $owner] = ownerWorkspace();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);

    $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create();

    Passport::actingAs($viewer);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/triggers")->assertOk();

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}", ['is_active' => false])
        ->assertForbidden();
});

it('404s updating a trigger that belongs to a different workspace', function () {
    [$workspace, $owner] = ownerWorkspace();
    [$otherWorkspace] = ownerWorkspace();

    $foreignTrigger = Trigger::factory()->webhook()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/triggers/{$foreignTrigger->id}", ['is_active' => false])
        ->assertNotFound();
});

it('rotates a webhook token, invalidating the old one', function () {
    [$workspace, $owner] = ownerWorkspace();
    $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create();
    $oldToken = $trigger->token;

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}/rotate-token");

    $response->assertOk();
    $newToken = $response->json('data.trigger.token');

    expect($newToken)->not->toBe($oldToken);
    $this->postJson("/api/hooks/{$oldToken}", [])->assertNotFound();
});

it('rejects rotating a token on a non-webhook trigger', function () {
    [$workspace, $owner] = ownerWorkspace();
    $trigger = Trigger::factory()->forWorkspace($workspace)->create(['type' => TriggerType::Manual]);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}/rotate-token")
        ->assertStatus(422);
});

it('queues a run from the manual endpoint', function () {
    [$workspace, $owner] = ownerWorkspace();
    $trigger = Trigger::factory()->forWorkspace($workspace)->create(['type' => TriggerType::Manual]);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}/run")
        ->assertStatus(202);

    expect($trigger->events()->count())->toBe(1);
});

it('lists a trigger\'s events newest first, paginated', function () {
    [$workspace, $owner] = ownerWorkspace();
    $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create();

    TriggerEvent::factory()->for($trigger, 'trigger')->count(3)->create();

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}/events");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
    expect($response->json('meta.total'))->toBe(3);
});

it('rejects a trigger pointed at another workspace\'s workflow', function () {
    [$workspace, $owner] = ownerWorkspace();
    [$otherWorkspace] = ownerWorkspace();
    $foreignWorkflow = Workflow::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers", [
        'target_type' => 'workflow',
        'target_id' => $foreignWorkflow->id,
        'type' => 'webhook',
    ])->assertJsonValidationErrors('target_id');
});

it('rejects a trigger endpoint that points at an internal address', function (string $url) {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->published()->create();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers", [
        'target_type' => 'workflow',
        'target_id' => $workflow->id,
        'type' => 'manual',
        'config' => ['url' => $url],
    ])->assertUnprocessable()->assertJsonValidationErrors('config.url');
})->with(['http://169.254.169.254/latest/meta-data/', 'http://127.0.0.1:6379/', 'file:///etc/passwd']);

it('rejects a schedule trigger with an invalid cron expression', function () {
    [$workspace, $owner] = ownerWorkspace();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers", [
        'target_type' => 'workflow',
        'target_id' => $workflow->id,
        'type' => 'schedule',
        'config' => ['cron' => 'every day'],
    ])->assertUnprocessable()->assertJsonValidationErrors('config.cron');
});

it('creates an app trigger for an agent that polls through a reader', function () {
    [$workspace, $owner] = ownerWorkspace();
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::factory()->create(['key' => 'github']))
        ->create(['scope' => ConnectorCredentialScope::Team, 'created_by' => $owner->id]);
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers", [
        'target_type' => 'agent',
        'target_id' => $agent->id,
        'type' => 'polling',
        'credential_id' => $credential->id,
        'config' => [
            'node_type' => 'github_list_issues',
            'node_config' => ['owner' => 'acme', 'repo' => 'app'],
            'poll_interval_minutes' => 5,
            'message' => 'Triage {{ payload.title }}',
        ],
    ]);

    $response->assertCreated();
    expect($response->json('data.trigger.config'))
        ->node_type->toBe('github_list_issues')
        ->node_config->toBe(['owner' => 'acme', 'repo' => 'app'])
        ->message->toBe('Triage {{ payload.title }}');
});

it('rejects an app trigger for a node that cannot act as a trigger', function () {
    [$workspace, $owner] = ownerWorkspace();
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers", [
        'target_type' => 'agent',
        'target_id' => $agent->id,
        'type' => 'polling',
        'config' => ['node_type' => 'ask_ai'],
    ])->assertUnprocessable()->assertJsonValidationErrors('config.node_type');
});

it('rejects another member\'s personal account on a trigger', function () {
    [$workspace, $owner] = ownerWorkspace();
    $member = User::factory()->create();
    $workspace->members()->create(['user_id' => $member->id, 'role' => Role::Admin, 'joined_at' => now()]);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $personal = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::factory()->create(['key' => 'github']))
        ->create(['scope' => ConnectorCredentialScope::Personal, 'created_by' => $owner->id]);
    Passport::actingAs($member);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers", [
        'target_type' => 'agent',
        'target_id' => $agent->id,
        'type' => 'polling',
        'credential_id' => $personal->id,
        'config' => ['node_type' => 'github_list_issues'],
    ])->assertUnprocessable()->assertJsonValidationErrors('credential_id');
});

it('switches a polling trigger to another account', function () {
    [$workspace, $owner] = ownerWorkspace();
    $connector = Connector::factory()->create(['key' => 'github']);
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)
        ->create(['scope' => ConnectorCredentialScope::Team, 'created_by' => $owner->id]);
    $trigger = Trigger::factory()->forWorkspace($workspace)->create([
        'type' => TriggerType::Polling,
        'config' => ['node_type' => 'github_list_issues'],
    ]);
    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}", ['credential_id' => $credential->id])
        ->assertOk();

    expect($trigger->fresh()->credential_id)->toBe($credential->id);
});

it('keeps an agent trigger\'s message when it is edited', function () {
    [$workspace, $owner] = ownerWorkspace();
    $trigger = Trigger::factory()->forWorkspace($workspace)->create([
        'type' => TriggerType::Schedule,
        'config' => ['cron' => '0 9 * * *'],
    ]);
    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}", [
        'config' => ['cron' => '0 9 * * *', 'message' => 'Post the daily summary'],
    ])->assertOk();

    expect($trigger->fresh()->config['message'])->toBe('Post the daily summary');
});

it('narrows a trigger\'s events to the statuses asked for', function () {
    [$workspace, $owner] = ownerWorkspace();
    $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create();

    TriggerEvent::factory()->for($trigger, 'trigger')->count(2)->create(['status' => TriggerEventStatus::Fired]);
    TriggerEvent::factory()->for($trigger, 'trigger')->create(['status' => TriggerEventStatus::Rejected]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}/events?status[]=rejected");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('rejected');
});

it('counts rejected events apart from skipped ones in the 24h stats', function () {
    [$workspace, $owner] = ownerWorkspace();
    $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create();

    TriggerEvent::factory()->for($trigger, 'trigger')->create(['status' => TriggerEventStatus::Rejected]);
    TriggerEvent::factory()->for($trigger, 'trigger')->create(['status' => TriggerEventStatus::Skipped]);

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/triggers")
        ->assertOk()
        ->assertJsonPath('data.triggers.0.stats_24h', ['fired' => 0, 'failed' => 0, 'skipped' => 1, 'rejected' => 1]);
});

it('caps how many events one page returns', function () {
    [$workspace, $owner] = ownerWorkspace();
    $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create();
    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}/events?per_page=5000")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});
