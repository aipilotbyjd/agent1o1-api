<?php

use App\Agents\Tools\ExportArtifactTool;
use App\Enums\Role;
use App\Models\Agent;
use App\Models\Artifact;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);

    $this->agent = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
});

test('exporting a filename creates version 1', function () {
    $tool = new ExportArtifactTool($this->agent, $this->workspace, 'conv-1', null, $this->user->id);

    $result = json_decode((string) $tool->handle(new Request([
        'filename' => 'report.html',
        'mime_type' => 'text/html',
        'content' => '<h1>Report</h1>',
    ])), true);

    expect($result['version'])->toBe(1)
        ->and($result['filename'])->toBe('report.html');

    $artifact = Artifact::first();
    Storage::disk('local')->assertExists($artifact->path);
});

test('re-exporting the same filename in the same conversation creates version 2', function () {
    $tool = new ExportArtifactTool($this->agent, $this->workspace, 'conv-1', null, $this->user->id);

    $tool->handle(new Request(['filename' => 'report.html', 'mime_type' => 'text/html', 'content' => 'v1']));
    $second = json_decode((string) $tool->handle(new Request([
        'filename' => 'report.html', 'mime_type' => 'text/html', 'content' => 'v2',
    ])), true);

    expect($second['version'])->toBe(2)
        ->and(Artifact::count())->toBe(2)
        ->and(Artifact::pluck('group_id')->unique())->toHaveCount(1);
});

test('the same filename in a different conversation starts its own group at version 1', function () {
    (new ExportArtifactTool($this->agent, $this->workspace, 'conv-1', null, $this->user->id))
        ->handle(new Request(['filename' => 'report.html', 'mime_type' => 'text/html', 'content' => 'a']));

    $other = json_decode((string) (new ExportArtifactTool($this->agent, $this->workspace, 'conv-2', null, $this->user->id))
        ->handle(new Request(['filename' => 'report.html', 'mime_type' => 'text/html', 'content' => 'b'])), true);

    expect($other['version'])->toBe(1)
        ->and(Artifact::pluck('group_id')->unique())->toHaveCount(2);
});

test('index returns only the latest version per group', function () {
    $tool = new ExportArtifactTool($this->agent, $this->workspace, 'conv-1', null, $this->user->id);
    $tool->handle(new Request(['filename' => 'report.html', 'mime_type' => 'text/html', 'content' => 'v1']));
    $tool->handle(new Request(['filename' => 'report.html', 'mime_type' => 'text/html', 'content' => 'v2']));

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/artifacts")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.version', 2);
});

test('download requires workspace membership', function () {
    $artifact = Artifact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'agent_id' => $this->agent->id,
    ]);
    Storage::disk('local')->put($artifact->path, 'content');

    $outsider = User::factory()->create();

    $this->actingAs($outsider, 'api')
        ->get("/api/v1/workspaces/{$this->workspace->id}/artifacts/{$artifact->id}/download")
        ->assertForbidden();

    $this->actingAs($this->user, 'api')
        ->get("/api/v1/workspaces/{$this->workspace->id}/artifacts/{$artifact->id}/download")
        ->assertOk();
});

test('preview requires a valid signature', function () {
    $artifact = Artifact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'agent_id' => $this->agent->id,
        'mime_type' => 'image/png',
    ]);
    Storage::disk('local')->put($artifact->path, 'fake-image-bytes');

    $signedUrl = URL::temporarySignedRoute('v1.artifacts.preview', now()->addMinutes(15), ['artifact' => $artifact->id]);

    $this->get($signedUrl)->assertOk();
    $this->get("/api/v1/artifacts/{$artifact->id}/preview")->assertForbidden();
});
