<?php

use App\Enums\RunStatus;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Runs\Run;
use App\Models\SuperAdmin\SuperAdminAccountNote;
use App\Models\SuperAdmin\SuperAdminAccountTag;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    $this->admin = superAdmin(SuperAdminRole::Support);
    $this->headers = superAdminHeaders($this->admin);
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

it('gives a workspace\'s billing, run counts, tags and last admin action in one read', function () {
    Run::factory()->forWorkspace($this->workspace)->count(3)->create(['status' => RunStatus::Completed, 'created_at' => now()->subHours(2)]);
    Run::factory()->forWorkspace($this->workspace)->create(['status' => RunStatus::Failed, 'created_at' => now()->subHours(3)]);
    Run::factory()->forWorkspace($this->workspace)->create(['status' => RunStatus::Failed, 'created_at' => now()->subDays(9)]);
    Run::factory()->forWorkspace($this->workspace)->create(['status' => RunStatus::Completed, 'created_at' => now()->subDays(40)]);
    SuperAdminAccountTag::factory()->create(['subject_type' => 'workspace', 'subject_id' => $this->workspace->id, 'tag' => 'watch']);
    SuperAdminAccountNote::factory()->count(2)->create(['subject_type' => 'workspace', 'subject_id' => $this->workspace->id]);
    app(SuperAdminAuditLogger::class)->record($this->admin, 'workspaces.suspended', $this->workspace, reason: 'Chargeback');

    $response = $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/overview"))->assertOk()
        ->assertJsonPath('data.type', 'workspace')
        ->assertJsonPath('data.tags', ['watch'])
        ->assertJsonPath('data.notes_count', 2)
        ->assertJsonPath('data.billing.workspace_id', $this->workspace->id)
        ->assertJsonPath('data.runs.last_7_days', 4)
        ->assertJsonPath('data.runs.failed_last_7_days', 1)
        ->assertJsonPath('data.runs.previous_7_days', 1)
        ->assertJsonPath('data.runs.failed_previous_7_days', 1)
        ->assertJsonPath('data.runs.last_24_hours', 4)
        ->assertJsonPath('data.runs.failed_last_24_hours', 1)
        ->assertJsonPath('data.last_admin_action.action', 'workspaces.suspended')
        ->assertJsonPath('data.last_admin_action.reason', 'Chargeback')
        ->assertJsonPath('data.refunds.count', 0)
        ->assertJsonPath('data.skipped', []);

    expect($response->json('data.runs.per_day'))->toHaveCount(14)
        ->and(collect($response->json('data.runs.per_day'))->sum('value'))->toBe(5);
});

it('counts every run, not a sample', function () {
    Run::factory()->forWorkspace($this->workspace)->count(120)->create(['status' => RunStatus::Completed, 'created_at' => now()->subHour()]);

    $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/overview"))->assertJsonPath('data.runs.last_7_days', 120);
});

it('counts refunds in the last 90 days and ignores older ones', function () {
    $logger = app(SuperAdminAuditLogger::class);
    $logger->record($this->admin, 'billing.refunded', $this->workspace);
    $logger->record($this->admin, 'billing.refunded', $this->workspace);
    $old = $logger->record($this->admin, 'billing.refunded', $this->workspace);
    SuperAdminAuditLog::query()->whereKey($old->id)->toBase()->update(['created_at' => now()->subDays(120)]);

    $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/overview"))->assertJsonPath('data.refunds.count', 2);
});

it('does not count the times someone only looked as the last thing done', function () {
    app(SuperAdminAuditLogger::class)->record($this->admin, 'workspaces.suspended', $this->workspace, reason: 'Fraud');
    app(SuperAdminAuditLogger::class)->record($this->admin, 'workspaces.viewed', $this->workspace);
    app(SuperAdminAuditLogger::class)->record($this->admin, 'billing.viewed', $this->workspace);

    $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/overview"))->assertJsonPath('data.last_admin_action.action', 'workspaces.suspended');
});

it('has no last action when only looking has happened', function () {
    $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/overview"))->assertJsonPath('data.last_admin_action', null);
});

it('gives a user their tags, run counts of what they started, and the last admin action', function () {
    Run::factory()->forWorkspace($this->workspace)->count(2)->create(['status' => RunStatus::Completed, 'triggered_by' => $this->owner->id, 'created_at' => now()->subHour()]);
    Run::factory()->forWorkspace($this->workspace)->create(['status' => RunStatus::Completed, 'created_at' => now()->subHour()]);
    SuperAdminAccountTag::factory()->create(['subject_type' => 'user', 'subject_id' => $this->owner->id, 'tag' => 'vip']);

    $this->withHeaders($this->headers)->getJson(saUrl("users/{$this->owner->id}/overview"))->assertOk()
        ->assertJsonPath('data.type', 'user')
        ->assertJsonPath('data.tags', ['vip'])
        ->assertJsonPath('data.runs.last_7_days', 2)
        ->assertJsonMissingPath('data.billing');
});

it('records the read once', function () {
    $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/overview"))->assertOk();

    expect(SuperAdminAuditLog::query()->where('subject_id', $this->workspace->id)->pluck('action')->all())->toBe(['workspaces.overview_viewed']);
});

it('is a 404 for a record that does not exist and needs the record\'s view ability', function () {
    $this->withHeaders($this->headers)->getJson(saUrl('workspaces/'.Str::uuid().'/overview'))->assertNotFound();
    $this->withHeaders($this->headers)->getJson(saUrl('users/'.Str::uuid().'/overview'))->assertNotFound();
    $this->flushHeaders()->getJson(saUrl("workspaces/{$this->workspace->id}/overview"))->assertUnauthorized();
});

it('never carries secrets', function () {
    $payload = $this->withHeaders($this->headers)->getJson(saUrl("users/{$this->owner->id}/overview"))->getContent();

    expect($payload)->not->toContain('password')->not->toContain('two_factor_secret')->not->toContain($this->owner->email);
});
