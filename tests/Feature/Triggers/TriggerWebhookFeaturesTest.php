<?php

use App\Enums\RunStatus;
use App\Enums\Triggers\TriggerEventStatus;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Triggers\TriggerService;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Queue;

/**
 * @param  array<string, mixed>  $config
 */
function webhookFeatureTrigger(array $config = [], ?string $secret = null): Trigger
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create(['config' => $config]);

    if ($secret !== null) {
        $trigger->forceFill(['signing_secret' => $secret])->save();
    }

    return $trigger;
}

/**
 * A published workflow behind a webhook trigger node, so a fired event really runs.
 *
 * @param  array<string, mixed>  $config
 * @return array{0: Workspace, 1: Trigger}
 */
function webhookFeaturePublished(array $config = []): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => 'webhook_trigger', 'config' => $config],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    return [$workspace, Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail()];
}

describe('idempotency', function () {
    beforeEach(function () {
        Queue::fake([FireTriggerEvent::class]);
    });

    it('treats a repeated Idempotency-Key as one delivery', function () {
        $trigger = webhookFeatureTrigger();

        $first = $this->postJson("/api/hooks/{$trigger->token}", ['a' => 1], ['Idempotency-Key' => 'order-42']);
        $second = $this->postJson("/api/hooks/{$trigger->token}", ['a' => 1], ['Idempotency-Key' => 'order-42']);

        expect($trigger->events()->count())->toBe(1)
            ->and($first->json('duplicate'))->toBeFalse()
            ->and($second->json('duplicate'))->toBeTrue()
            ->and($second->json('event_id'))->toBe($first->json('event_id'));
        Queue::assertPushed(FireTriggerEvent::class, 1);
    });

    it('also reads X-Request-Id', function () {
        $trigger = webhookFeatureTrigger();

        $this->postJson("/api/hooks/{$trigger->token}", [], ['X-Request-Id' => 'req-1']);
        $this->postJson("/api/hooks/{$trigger->token}", [], ['X-Request-Id' => 'req-1']);

        expect($trigger->events()->count())->toBe(1);
    });

    it('keeps requests without a key separate', function () {
        $trigger = webhookFeatureTrigger();

        $this->postJson("/api/hooks/{$trigger->token}", []);
        $this->postJson("/api/hooks/{$trigger->token}", []);

        expect($trigger->events()->count())->toBe(2);
    });
});

describe('payload', function () {
    beforeEach(function () {
        Queue::fake([FireTriggerEvent::class]);
    });

    it('keeps a plain-text body whole under body', function () {
        $trigger = webhookFeatureTrigger();

        $this->call('POST', "/api/hooks/{$trigger->token}", [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'hello there')->assertOk();

        expect($trigger->events()->first()->payload)->toBe(['body' => 'hello there']);
    });

    it('keeps an XML body whole under body', function () {
        $trigger = webhookFeatureTrigger();

        $this->call('POST', "/api/hooks/{$trigger->token}", [], [], [], ['CONTENT_TYPE' => 'application/xml'], '<a>1</a>')->assertOk();

        expect($trigger->events()->first()->payload)->toBe(['body' => '<a>1</a>']);
    });

    it('reads a form body as fields', function () {
        $trigger = webhookFeatureTrigger();

        $this->post("/api/hooks/{$trigger->token}", ['name' => 'Ada'])->assertOk();

        expect($trigger->events()->first()->payload)->toBe(['name' => 'Ada']);
    });

    it('passes only the named headers, and never a credential header', function () {
        $trigger = webhookFeatureTrigger(['pass_headers' => ['X-Order-Source', 'Authorization']]);

        $this->postJson("/api/hooks/{$trigger->token}", ['a' => 1], ['X-Order-Source' => 'shop', 'Authorization' => 'Bearer secret', 'X-Other' => 'no'])->assertOk();

        expect($trigger->events()->first()->payload)->toBe(['a' => 1, '_headers' => ['x-order-source' => 'shop']]);
    });
});

describe('verification challenges', function () {
    beforeEach(function () {
        Queue::fake([FireTriggerEvent::class]);
    });

    it('echoes the challenge of a Slack URL verification without starting anything', function () {
        $trigger = webhookFeatureTrigger();

        $this->postJson("/api/hooks/{$trigger->token}", ['type' => 'url_verification', 'challenge' => 'abc123'])
            ->assertOk()
            ->assertExactJson(['challenge' => 'abc123']);

        expect($trigger->events()->count())->toBe(0);
    });

    it('answers a Meta GET challenge when the verify token matches', function () {
        $trigger = webhookFeatureTrigger(secret: 'my-verify-token');

        $this->get("/api/hooks/{$trigger->token}?hub.mode=subscribe&hub.verify_token=my-verify-token&hub.challenge=1158201444")
            ->assertOk()
            ->assertSee('1158201444');
    });

    it('refuses a GET challenge with the wrong token', function () {
        $trigger = webhookFeatureTrigger(secret: 'my-verify-token');

        $this->get("/api/hooks/{$trigger->token}?hub.mode=subscribe&hub.verify_token=nope&hub.challenge=1")->assertForbidden();
    });

    it('404s a GET when the trigger has no secret to check against', function () {
        $trigger = webhookFeatureTrigger();

        $this->get("/api/hooks/{$trigger->token}?hub.mode=subscribe&hub.verify_token=x&hub.challenge=1")->assertNotFound();
    });
});

describe('responding with the result', function () {
    it('returns the run id and its result when the run finishes in time', function () {
        [, $trigger] = webhookFeaturePublished(['response_mode' => 'wait']);

        $response = $this->postJson("/api/hooks/{$trigger->token}", ['a' => 1]);

        $response->assertOk()->assertJson(['status' => 'completed']);
        expect($response->json('run_id'))->not->toBeNull();
    });

    it('answers 202 with the event id when the run has not started in time', function () {
        Queue::fake([FireTriggerEvent::class]);
        [, $trigger] = webhookFeaturePublished(['response_mode' => 'wait', 'response_timeout_seconds' => 1]);

        $this->postJson("/api/hooks/{$trigger->token}", ['a' => 1])
            ->assertStatus(202)
            ->assertJson(['status' => 'queued']);
    });

    it('answers right away with the event id by default', function () {
        Queue::fake([FireTriggerEvent::class]);
        $trigger = webhookFeatureTrigger();

        $this->postJson("/api/hooks/{$trigger->token}", [])
            ->assertOk()
            ->assertJsonStructure(['status', 'event_id', 'run_id', 'duplicate']);
    });
});

describe('concurrency caps', function () {
    it('leaves an event queued while the workspace is at its cap, then runs it', function () {
        [$workspace, $trigger] = webhookFeaturePublished();
        $workflow = Workflow::query()->findOrFail($trigger->target_id);

        $busy = $workflow->runs()->create([
            'workspace_id' => $workspace->id,
            'runnable_type' => $workflow->getMorphClass(),
            'runnable_id' => $workflow->id,
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $workflow->current_version_id,
            'trigger_type' => 'manual',
            'input' => [],
        ]);
        $busy->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

        config(['triggers.max_concurrent_runs_per_workspace' => 1]);
        Queue::fake([FireTriggerEvent::class]);
        $this->postJson("/api/hooks/{$trigger->token}", ['a' => 1])->assertOk();
        $event = $trigger->events()->firstOrFail();

        (new FireTriggerEvent($trigger, $event))->handle(app(TriggerService::class));

        expect($event->fresh()->status)->toBe(TriggerEventStatus::Queued);

        $busy->forceFill(['status' => RunStatus::Completed])->save();
        (new FireTriggerEvent($trigger, $event->fresh()))->handle(app(TriggerService::class));

        expect($event->fresh()->status)->toBe(TriggerEventStatus::Fired);
    });

    it('honours a trigger\'s own cap', function () {
        [$workspace, $trigger] = webhookFeaturePublished(['max_concurrent_runs' => 1]);
        $workflow = Workflow::query()->findOrFail($trigger->target_id);
        $workflow->runs()->create([
            'workspace_id' => $workspace->id,
            'runnable_type' => $workflow->getMorphClass(),
            'runnable_id' => $workflow->id,
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $workflow->current_version_id,
            'trigger_type' => 'manual',
            'input' => [],
        ])->forceFill(['status' => RunStatus::Running])->save();

        expect(app(TriggerService::class)->atConcurrencyLimit($trigger->fresh()))->toBeTrue();
    });
});

describe('who a trigger acts as', function () {
    it('uses the creator while they are in the workspace', function () {
        $trigger = webhookFeatureTrigger();
        $owner = $trigger->workspace->owner;
        $trigger->forceFill(['created_by' => $owner->id])->save();

        expect($trigger->fresh()->actingUser()->is($owner))->toBeTrue();
    });

    it('falls back to the workspace owner once the creator is not a member', function () {
        $trigger = webhookFeatureTrigger();
        $stranger = User::factory()->create();
        $trigger->forceFill(['created_by' => $stranger->id])->save();

        expect($trigger->fresh()->actingUser()->is($trigger->workspace->owner))->toBeTrue();
    });
});
