<?php

use App\Actions\Artifacts\StoreArtifactAction;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Artifacts\Artifact;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForUploads(): array
{
    $owner = User::factory()->create();

    return [app(WorkspaceService::class)->create($owner, ['name' => 'Acme']), $owner];
}

it('uploads a file as a version-1 artifact', function () {
    Storage::fake('local');
    [$workspace, $owner] = ownerWorkspaceForUploads();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/artifacts", [
        'file' => UploadedFile::fake()->create('report.pdf', 12, 'application/pdf'),
    ]);

    $response->assertCreated();

    $artifact = Artifact::sole();

    expect($response->json('data.artifact.filename'))->toBe('report.pdf');
    expect($response->json('data.artifact.version'))->toBe(1);
    // An upload has no agent behind it, unlike an agent's own export.
    expect($response->json('data.artifact.agent'))->toBeNull();
    expect($artifact->created_by)->toBe($owner->id);
    expect($artifact->agent_id)->toBeNull();
    Storage::disk('local')->assertExists($artifact->path);
});

it('files a re-uploaded filename as the next version of the same group', function () {
    Storage::fake('local');
    [$workspace, $owner] = ownerWorkspaceForUploads();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/artifacts", [
        'file' => UploadedFile::fake()->create('report.pdf', 12, 'application/pdf'),
    ])->assertCreated();

    $second = $this->postJson("/api/v1/workspaces/{$workspace->id}/artifacts", [
        'file' => UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'),
    ]);

    $second->assertCreated();
    expect($second->json('data.artifact.version'))->toBe(2);
    expect(Artifact::pluck('group_id')->unique())->toHaveCount(1);

    // Both versions survive: a re-upload never overwrites.
    expect(Artifact::count())->toBe(2);
    expect(Storage::disk('local')->allFiles())->toHaveCount(2);

    // The list only surfaces the newest version of the group.
    $list = $this->getJson("/api/v1/workspaces/{$workspace->id}/artifacts");
    expect($list->json('data'))->toHaveCount(1);
    expect($list->json('data.0.version'))->toBe(2);
});

it('adds a version to an explicitly named group even under a different filename', function () {
    Storage::fake('local');
    [$workspace, $owner] = ownerWorkspaceForUploads();
    Passport::actingAs($owner);

    $first = $this->postJson("/api/v1/workspaces/{$workspace->id}/artifacts", [
        'file' => UploadedFile::fake()->create('report.pdf', 12, 'application/pdf'),
    ]);

    $groupId = $first->json('data.artifact.group_id');

    $second = $this->postJson("/api/v1/workspaces/{$workspace->id}/artifacts", [
        'file' => UploadedFile::fake()->create('report-final.pdf', 12, 'application/pdf'),
        'group_id' => $groupId,
    ]);

    $second->assertCreated();
    expect($second->json('data.artifact.group_id'))->toBe($groupId);
    expect($second->json('data.artifact.version'))->toBe(2);
});

it('attributes an upload to an agent when one is given', function () {
    Storage::fake('local');
    [$workspace, $owner] = ownerWorkspaceForUploads();
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/artifacts", [
        'file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        'agent_id' => $agent->id,
        'filename' => 'renamed.txt',
        'metadata' => ['origin' => 'manual'],
    ]);

    $response->assertCreated();
    expect($response->json('data.artifact.agent.id'))->toBe($agent->id);
    expect($response->json('data.artifact.filename'))->toBe('renamed.txt');
    expect(Artifact::sole()->metadata)->toBe(['origin' => 'manual']);
});

it('rejects an agent from another workspace', function () {
    Storage::fake('local');
    [$workspace, $owner] = ownerWorkspaceForUploads();
    [$otherWorkspace] = ownerWorkspaceForUploads();
    $foreignAgent = Agent::factory()->forWorkspace($otherWorkspace)->create();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/artifacts", [
        'file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        'agent_id' => $foreignAgent->id,
    ])->assertJsonValidationErrors('agent_id');
});

it('rejects a file over the configured size limit', function () {
    Storage::fake('local');
    config(['artifacts.max_upload_kilobytes' => 10]);
    [$workspace, $owner] = ownerWorkspaceForUploads();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/artifacts", [
        'file' => UploadedFile::fake()->create('big.pdf', 64, 'application/pdf'),
    ])->assertJsonValidationErrors('file');

    expect(Artifact::count())->toBe(0);
});

it('keeps a traversal filename inside the workspace artifact directory', function () {
    Storage::fake('local');
    [$workspace, $owner] = ownerWorkspaceForUploads();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/artifacts", [
        'file' => UploadedFile::fake()->create('payload.txt', 1, 'text/plain'),
        'filename' => '../../../.env',
    ])->assertCreated();

    $artifact = Artifact::sole();

    // The supplied name is kept as the display filename, but never decides
    // where the bytes land.
    expect($artifact->filename)->toBe('../../../.env');
    expect($artifact->path)->toStartWith("artifacts/{$workspace->id}/");
    expect($artifact->path)->not->toContain('..');
    Storage::disk('local')->assertExists($artifact->path);
});

it('does not let a viewer upload', function () {
    Storage::fake('local');
    [$workspace] = ownerWorkspaceForUploads();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    Passport::actingAs($viewer);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/artifacts", [
        'file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
    ])->assertForbidden();
});

it('restores a soft-deleted group and continues its version history on re-upload', function () {
    Storage::fake('local');
    [$workspace, $owner] = ownerWorkspaceForUploads();
    Passport::actingAs($owner);

    $first = $this->postJson("/api/v1/workspaces/{$workspace->id}/artifacts", [
        'file' => UploadedFile::fake()->create('report.pdf', 12, 'application/pdf'),
    ]);
    $groupId = $first->json('data.artifact.group_id');

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/artifacts/{$first->json('data.artifact.id')}")
        ->assertNoContent();
    expect($this->getJson("/api/v1/workspaces/{$workspace->id}/artifacts")->json('data'))->toHaveCount(0);

    $second = $this->postJson("/api/v1/workspaces/{$workspace->id}/artifacts", [
        'file' => UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'),
    ]);

    $second->assertCreated();
    expect($second->json('data.artifact.group_id'))->toBe($groupId);
    expect($second->json('data.artifact.version'))->toBe(2);
    expect(Artifact::where('group_id', $groupId)->count())->toBe(2);
});

it('sandboxes a previewed html artifact', function () {
    Storage::fake('local');
    [$workspace, $owner] = ownerWorkspaceForUploads();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/artifacts", [
        'file' => UploadedFile::fake()->createWithContent('page.html', '<h1>hi</h1>'),
    ])->assertCreated();

    $previewUrl = $this->getJson("/api/v1/workspaces/{$workspace->id}/artifacts/".Artifact::sole()->id)
        ->json('data.artifact.preview_url');

    $response = $this->get($previewUrl);

    $response->assertOk();
    expect($response->headers->get('Content-Security-Policy'))->toBe('sandbox');
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('refuses two artifacts sharing a version in one group', function () {
    Storage::fake('local');
    [$workspace, $owner] = ownerWorkspaceForUploads();
    $attributes = [
        'workspace_id' => $workspace->id, 'created_by' => $owner->id, 'group_id' => (string) Str::uuid(), 'version' => 1,
        'filename' => 'a.txt', 'mime_type' => 'text/plain', 'size' => 1, 'disk' => 'local',
    ];

    Artifact::create([...$attributes, 'path' => 'artifacts/a-1.txt']);

    expect(fn () => Artifact::create([...$attributes, 'path' => 'artifacts/a-2.txt']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('leaves no artifact row or staged file behind when the bytes cannot be stored', function () {
    Storage::fake('local');
    [$workspace] = ownerWorkspaceForUploads();

    Storage::shouldReceive('disk')->with('local')->andReturn(new class
    {
        public function put(): bool
        {
            return false;
        }
    });

    expect(fn () => app(StoreArtifactAction::class)->execute($workspace, 'a.txt', 'text/plain', 'hello'))
        ->toThrow(RuntimeException::class);

    expect(Artifact::count())->toBe(0);
});
