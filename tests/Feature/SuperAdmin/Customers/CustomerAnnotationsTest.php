<?php

use App\Enums\RunStatus;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Runs\Run;
use App\Models\SuperAdmin\SuperAdminAccountNote;
use App\Models\SuperAdmin\SuperAdminAccountTag;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = superAdmin(SuperAdminRole::Support);
    $this->headers = superAdminHeaders($this->admin);
    $this->owner = User::factory()->create(['email' => 'owner@acme.test']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

it('adds a note to a user and to a workspace and lists them newest first', function () {
    $this->withHeaders($this->headers)->postJson(saUrl("users/{$this->owner->id}/notes"), ['body' => 'Asked for a refund by email'])
        ->assertCreated()->assertJsonPath('data.body', 'Asked for a refund by email')->assertJsonPath('data.author.email', $this->admin->email);
    $this->withHeaders($this->headers)->postJson(saUrl("users/{$this->owner->id}/notes"), ['body' => 'Refund approved by finance'])->assertCreated();
    $this->withHeaders($this->headers)->postJson(saUrl("workspaces/{$this->workspace->id}/notes"), ['body' => 'Enterprise trial'])->assertCreated();

    $this->withHeaders($this->headers)->getJson(saUrl("users/{$this->owner->id}/annotations"))->assertOk()
        ->assertJsonCount(2, 'data.notes')
        ->assertJsonPath('data.notes.0.body', 'Refund approved by finance');
    $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/annotations"))->assertJsonCount(1, 'data.notes');
});

it('audits a note without copying its text', function () {
    $this->withHeaders($this->headers)->postJson(saUrl("users/{$this->owner->id}/notes"), ['body' => 'Private remark about the customer'])->assertCreated();

    $entry = SuperAdminAuditLog::query()->where('action', 'customers.note_added')->firstOrFail();

    expect($entry->subject_type)->toBe('user')->and($entry->subject_id)->toBe($this->owner->id)
        ->and(json_encode($entry->after))->not->toContain('Private remark');
});

it('validates a note', function () {
    $this->withHeaders($this->headers)->postJson(saUrl("users/{$this->owner->id}/notes"), [])->assertUnprocessable()->assertJsonValidationErrors('body');
    $this->withHeaders($this->headers)->postJson(saUrl("users/{$this->owner->id}/notes"), ['body' => str_repeat('x', 2001)])->assertUnprocessable();
});

it('lets an author delete their own note but not someone else\'s, and an owner delete any', function () {
    $mine = SuperAdminAccountNote::factory()->create(['subject_type' => 'user', 'subject_id' => $this->owner->id, 'super_admin_id' => $this->admin->id]);
    $theirs = SuperAdminAccountNote::factory()->create(['subject_type' => 'user', 'subject_id' => $this->owner->id, 'super_admin_id' => superAdmin(SuperAdminRole::Finance)->id]);

    $this->withHeaders($this->headers)->deleteJson(saUrl("users/{$this->owner->id}/notes/{$theirs->id}"))->assertForbidden();
    $this->withHeaders($this->headers)->deleteJson(saUrl("users/{$this->owner->id}/notes/{$mine->id}"))->assertOk();

    expect(SuperAdminAccountNote::query()->whereKey($mine->id)->exists())->toBeFalse()
        ->and(SuperAdminAccountNote::query()->whereKey($theirs->id)->exists())->toBeTrue();

    $this->withHeaders(superAdminHeaders(superAdmin(SuperAdminRole::Owner)))->deleteJson(saUrl("users/{$this->owner->id}/notes/{$theirs->id}"))->assertOk();

    expect(SuperAdminAuditLog::query()->where('action', 'customers.note_deleted')->count())->toBe(2);
});

it('does not delete a note through another record', function () {
    $note = SuperAdminAccountNote::factory()->create(['subject_type' => 'user', 'subject_id' => $this->owner->id, 'super_admin_id' => $this->admin->id]);

    $this->withHeaders($this->headers)->deleteJson(saUrl("workspaces/{$this->workspace->id}/notes/{$note->id}"))->assertNotFound();
});

it('sets the tags of a record to exactly the list, lower-cased and de-duplicated', function () {
    $this->withHeaders($this->headers)->putJson(saUrl("workspaces/{$this->workspace->id}/tags"), ['tags' => ['VIP', 'watch', 'vip', ' Do Not Refund ']])
        ->assertOk()->assertJsonPath('data.tags', ['do not refund', 'vip', 'watch']);

    $this->withHeaders($this->headers)->putJson(saUrl("workspaces/{$this->workspace->id}/tags"), ['tags' => ['vip']])->assertOk()->assertJsonPath('data.tags', ['vip']);

    expect(SuperAdminAccountTag::query()->where('subject_id', $this->workspace->id)->pluck('tag')->all())->toBe(['vip']);

    $this->withHeaders($this->headers)->putJson(saUrl("workspaces/{$this->workspace->id}/tags"), ['tags' => []])->assertOk()->assertJsonPath('data.tags', []);
});

it('audits only real tag changes, with before and after', function () {
    $this->withHeaders($this->headers)->putJson(saUrl("users/{$this->owner->id}/tags"), ['tags' => ['vip']])->assertOk();
    $this->withHeaders($this->headers)->putJson(saUrl("users/{$this->owner->id}/tags"), ['tags' => ['vip']])->assertOk();
    $this->withHeaders($this->headers)->putJson(saUrl("users/{$this->owner->id}/tags"), ['tags' => ['vip', 'watch']])->assertOk();

    $entries = SuperAdminAuditLog::query()->where('action', 'customers.tags_changed')->orderBy('sequence')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[1]->before)->toBe(['tags' => ['vip']])
        ->and($entries[1]->after)->toBe(['tags' => ['vip', 'watch']]);
});

it('rejects bad tags and too many', function () {
    $url = saUrl("users/{$this->owner->id}/tags");

    $this->withHeaders($this->headers)->putJson($url, ['tags' => ['<script>']])->assertUnprocessable();
    $this->withHeaders($this->headers)->putJson($url, ['tags' => ['a']])->assertUnprocessable();
    $this->withHeaders($this->headers)->putJson($url, ['tags' => [str_repeat('x', 25)]])->assertUnprocessable();
    $this->withHeaders($this->headers)->putJson($url, ['tags' => array_map(fn ($i) => "tag-{$i}", range(1, 9))])->assertUnprocessable();
    $this->withHeaders($this->headers)->putJson($url, [])->assertUnprocessable();
});

it('shows tags on the user and workspace lists and details, and filters by tag', function () {
    $other = User::factory()->create();
    SuperAdminAccountTag::factory()->create(['subject_type' => 'user', 'subject_id' => $this->owner->id, 'tag' => 'vip']);
    SuperAdminAccountTag::factory()->create(['subject_type' => 'workspace', 'subject_id' => $this->workspace->id, 'tag' => 'vip']);

    $users = $this->withHeaders($this->headers)->getJson(saUrl('users'))->assertOk();
    expect(collect($users->json('data'))->firstWhere('id', $this->owner->id)['tags'])->toBe(['vip'])
        ->and(collect($users->json('data'))->firstWhere('id', $other->id)['tags'])->toBe([]);

    $this->withHeaders($this->headers)->getJson(saUrl('users?tag=VIP'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->owner->id);
    $this->withHeaders($this->headers)->getJson(saUrl('workspaces?tag=vip'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->workspace->id);
    $this->withHeaders($this->headers)->getJson(saUrl('workspaces?tag=nothing'))->assertJsonCount(0, 'data');
    $this->withHeaders($this->headers)->getJson(saUrl("users/{$this->owner->id}"))->assertJsonPath('data.tags', ['vip']);
    $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}"))->assertJsonPath('data.tags', ['vip']);
});

it('lets the roles that annotate do so and keeps viewers read-only', function (SuperAdminRole $role, int $status) {
    $headers = superAdminHeaders(superAdmin($role));

    $this->withHeaders($headers)->postJson(saUrl("users/{$this->owner->id}/notes"), ['body' => 'A note'])->assertStatus($status);
    $this->withHeaders($headers)->putJson(saUrl("users/{$this->owner->id}/tags"), ['tags' => ['vip']])->assertStatus($status === 201 ? 200 : $status);
    $this->withHeaders($headers)->getJson(saUrl("users/{$this->owner->id}/annotations"))->assertOk();
})->with([
    'owner' => [SuperAdminRole::Owner, 201],
    'operator' => [SuperAdminRole::Operator, 201],
    'finance' => [SuperAdminRole::Finance, 201],
    'support' => [SuperAdminRole::Support, 201],
    'viewer' => [SuperAdminRole::Viewer, 403],
]);

it('is a 404 for a record that does not exist', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('users/'.Str::uuid().'/notes'), ['body' => 'Hello'])->assertNotFound();
    $this->withHeaders($this->headers)->putJson(saUrl('workspaces/'.Str::uuid().'/tags'), ['tags' => ['vip']])->assertNotFound();
});

it('filters runs by the user who started them', function () {
    Run::factory()->forWorkspace($this->workspace)->create(['status' => RunStatus::Completed, 'triggered_by' => $this->owner->id]);
    Run::factory()->forWorkspace($this->workspace)->create(['status' => RunStatus::Completed]);

    $this->withHeaders($this->headers)->getJson(saUrl("runs?user_id={$this->owner->id}"))->assertOk()->assertJsonCount(1, 'data');
    $this->withHeaders($this->headers)->getJson(saUrl('runs?user_id=not-a-uuid'))->assertUnprocessable();
});
