<?php

use App\Enums\Triggers\TriggerEventStatus;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Queue;

function makeWebhookTrigger(array $overrides = []): Trigger
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return Trigger::factory()->webhook()->forWorkspace($workspace)->create($overrides);
}

it('accepts a valid webhook post, queues an event, and dispatches the fire job', function () {
    Queue::fake();

    $trigger = makeWebhookTrigger();

    $this->postJson("/api/hooks/{$trigger->token}", ['hello' => 'world'])
        ->assertOk()
        ->assertJson(['status' => 'accepted']);

    $event = $trigger->events()->first();

    expect($event)->not->toBeNull();
    expect($event->status)->toBe(TriggerEventStatus::Queued);

    Queue::assertPushed(FireTriggerEvent::class);
});

it('404s on an unknown token', function () {
    $this->postJson('/api/hooks/does-not-exist', [])->assertNotFound();
});

it('404s on an inactive trigger', function () {
    $trigger = makeWebhookTrigger(['is_active' => false]);

    $this->postJson("/api/hooks/{$trigger->token}", [])->assertNotFound();
});
