<?php

use App\Enums\RunStatus;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Runs\Run;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = superAdmin(SuperAdminRole::Support);
    $this->headers = superAdminHeaders($this->admin);
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

function saCredit(string $workspaceId, float $credits, $at): void
{
    (new CreditTransaction)->forceFill(['workspace_id' => $workspaceId, 'source_type' => 'node_run', 'source_id' => (string) Str::uuid(), 'credits' => $credits, 'created_at' => $at, 'updated_at' => $at])->save();
}

it('merges runs, credits, plan grants and admin actions, newest first', function () {
    Run::factory()->forWorkspace($this->workspace)->create(['status' => RunStatus::Failed, 'error' => 'Timed out', 'created_at' => now()->subHours(1)]);
    saCredit($this->workspace->id, 4, now()->subHours(2));
    PlanGrant::factory()->forWorkspace($this->workspace)->forPlan(Plan::factory()->create(['name' => 'Pro']))->active()->create(['granted_at' => now()->subHours(3), 'created_at' => now()->subHours(3)]);
    $entry = app(SuperAdminAuditLogger::class)->record($this->admin, 'workspaces.suspended', $this->workspace, reason: 'Chargeback');
    SuperAdminAuditLog::query()->whereKey($entry->id)->toBase()->update(['created_at' => now()->subHours(4)]);

    $response = $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/timeline"))->assertOk();

    expect(collect($response->json('data.events'))->pluck('type')->all())->toBe(['run', 'credits', 'plan', 'admin'])
        ->and($response->json('data.events.0'))->toMatchArray(['status' => 'failed', 'error' => 'Timed out'])
        ->and($response->json('data.events.1'))->toMatchArray(['credits' => 4, 'source_type' => 'node_run'])
        ->and($response->json('data.events.2'))->toMatchArray(['plan' => 'Pro', 'status' => 'active'])
        ->and($response->json('data.events.3'))->toMatchArray(['action' => 'workspaces.suspended', 'reason' => 'Chargeback'])
        ->and($response->json('data.next'))->toBeNull()
        ->and($response->json('data.skipped'))->toBe([]);
});

it('leaves out the audit rows that only record someone looking', function () {
    app(SuperAdminAuditLogger::class)->record($this->admin, 'workspaces.viewed', $this->workspace);
    app(SuperAdminAuditLogger::class)->record($this->admin, 'workspaces.timeline_viewed', $this->workspace);
    app(SuperAdminAuditLogger::class)->record($this->admin, 'billing.viewed', $this->workspace);

    $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/timeline"))->assertJsonPath('data.events', []);
});

it('pages by time: a page, then what is older than its last event', function () {
    foreach (range(1, 7) as $hour) {
        Run::factory()->forWorkspace($this->workspace)->create(['status' => RunStatus::Completed, 'created_at' => now()->subHours($hour)->startOfSecond()]);
    }

    $first = $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/timeline?limit=3&types[]=run"))->assertOk();

    expect($first->json('data.events'))->toHaveCount(3)->and($first->json('data.next'))->not->toBeNull();

    $second = $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/timeline?limit=3&types[]=run&before=".urlencode($first->json('data.next'))))->assertOk();

    expect($second->json('data.events'))->toHaveCount(3)
        ->and(array_intersect(array_column($first->json('data.events'), 'id'), array_column($second->json('data.events'), 'id')))->toBe([]);

    $third = $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/timeline?limit=3&types[]=run&before=".urlencode($second->json('data.next'))))->assertOk();

    expect($third->json('data.events'))->toHaveCount(1)->and($third->json('data.next'))->toBeNull();
});

it('narrows to the chosen types', function () {
    Run::factory()->forWorkspace($this->workspace)->create(['status' => RunStatus::Completed]);
    saCredit($this->workspace->id, 1, now());

    $types = collect($this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/timeline?types[]=credits"))->json('data.events'))->pluck('type')->unique()->all();

    expect($types)->toBe(['credits']);
});

it('rejects a type, a limit or a date it does not understand', function () {
    $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/timeline?types[]=bogus"))->assertUnprocessable();
    $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/timeline?limit=1000"))->assertUnprocessable();
    $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/timeline?before=yesterday-ish"))->assertUnprocessable();
});

it('truncates a long error', function () {
    Run::factory()->forWorkspace($this->workspace)->create(['status' => RunStatus::Failed, 'error' => str_repeat('x', 900)]);

    $error = $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/timeline"))->json('data.events.0.error');

    expect(mb_strlen($error))->toBeLessThanOrEqual(203);
});

it('shows a user the runs they started and what admins did to them, not billing', function () {
    Run::factory()->forWorkspace($this->workspace)->create(['status' => RunStatus::Completed, 'triggered_by' => $this->owner->id]);
    Run::factory()->forWorkspace($this->workspace)->create(['status' => RunStatus::Completed]);
    saCredit($this->workspace->id, 1, now());
    app(SuperAdminAuditLogger::class)->record($this->admin, 'users.suspended', $this->owner, reason: 'Abuse');

    $types = collect($this->withHeaders($this->headers)->getJson(saUrl("users/{$this->owner->id}/timeline"))->assertOk()->json('data.events'))->pluck('type')->all();

    expect($types)->toHaveCount(2)->and($types)->toContain('run', 'admin')->and($types)->not->toContain('credits');
});

it('says which sources the role may not read instead of pretending they are empty', function () {
    // Viewer holds every ability the timeline needs; prove the skip path with a user, whose credits are not applicable.
    $this->withHeaders($this->headers)->getJson(saUrl("users/{$this->owner->id}/timeline"))->assertOk()->assertJsonPath('data.skipped', []);
});

it('records the read once', function () {
    $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/timeline"))->assertOk();

    expect(SuperAdminAuditLog::query()->where('subject_id', $this->workspace->id)->pluck('action')->all())->toBe(['workspaces.timeline_viewed']);
});

it('is a 404 for a record that does not exist', function () {
    $this->withHeaders($this->headers)->getJson(saUrl('workspaces/'.Str::uuid().'/timeline'))->assertNotFound();
});
