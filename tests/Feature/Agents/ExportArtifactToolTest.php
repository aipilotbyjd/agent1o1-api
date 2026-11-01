<?php

use App\Actions\Artifacts\StoreArtifactAction;
use App\Ai\Tools\ExportArtifactTool;
use App\Models\Agents\Agent;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Artifacts\Artifact;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request;

it('creates a new artifact on first export', function () {
    Storage::fake('local');
    // text/plain export gets indexed into the agent's artifact knowledge
    // collection: see StoreArtifactAction::indexForAgent().
    Embeddings::fake([[[1.0, 0.0]]]);
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $run = $session->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);

    $tool = new ExportArtifactTool($agent, $session, $run, app(StoreArtifactAction::class));
    $result = json_decode($tool->handle(new Request([
        'filename' => 'report.txt',
        'mime_type' => 'text/plain',
        'content' => 'hello world',
    ])), true);

    expect($result['version'])->toBe(1);
    $artifact = Artifact::sole();
    expect($artifact->filename)->toBe('report.txt');
    expect($artifact->agent_session_id)->toBe($session->id);
    expect($artifact->run_id)->toBe($run->id);
    expect($artifact->created_by)->toBe($owner->id);
    Storage::disk('local')->assertExists($artifact->path);
});

it('creates a new version instead of overwriting on re-export', function () {
    Storage::fake('local');
    // Each export indexes the artifact afresh: two calls, two embed calls.
    Embeddings::fake([[[1.0, 0.0]], [[1.0, 0.0]]]);
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $run = $session->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);
    $tool = new ExportArtifactTool($agent, $session, $run, app(StoreArtifactAction::class));

    $tool->handle(new Request(['filename' => 'report.txt', 'mime_type' => 'text/plain', 'content' => 'v1']));
    $result = json_decode($tool->handle(new Request([
        'filename' => 'report.txt', 'mime_type' => 'text/plain', 'content' => 'v2',
    ])), true);

    expect($result['version'])->toBe(2);
    expect(Artifact::count())->toBe(2);
    expect(Artifact::distinct()->pluck('group_id'))->toHaveCount(1);
});

it('decodes base64 content', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $run = $session->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);
    $tool = new ExportArtifactTool($agent, $session, $run, app(StoreArtifactAction::class));

    $tool->handle(new Request([
        'filename' => 'image.png',
        'mime_type' => 'image/png',
        'content' => base64_encode('binary-data'),
        'is_base64' => true,
    ]));

    $artifact = Artifact::sole();
    Storage::disk('local')->assertExists($artifact->path);
    expect(Storage::disk('local')->get($artifact->path))->toBe('binary-data');
});

it('indexes a text export into the agent\'s own artifact knowledge collection', function () {
    Storage::fake('local');
    Embeddings::fake([[[1.0, 0.0]]]);
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $run = $session->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);
    $tool = new ExportArtifactTool($agent, $session, $run, app(StoreArtifactAction::class));

    $tool->handle(new Request([
        'filename' => 'report.txt', 'mime_type' => 'text/plain', 'content' => 'Q3 revenue grew 12%.',
    ]));

    $chunk = DocumentEmbedding::sole();
    expect($chunk->collection)->toBe($agent->artifactKnowledgeCollection());
    expect($chunk->source)->toBe('report.txt');
    expect($chunk->chunk_text)->toBe('Q3 revenue grew 12%.');
});

it('replaces the indexed chunks rather than accumulating them on re-export', function () {
    Storage::fake('local');
    Embeddings::fake([[[1.0, 0.0]], [[1.0, 0.0]]]);
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $run = $session->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);
    $tool = new ExportArtifactTool($agent, $session, $run, app(StoreArtifactAction::class));

    $tool->handle(new Request(['filename' => 'report.txt', 'mime_type' => 'text/plain', 'content' => 'v1 content']));
    $tool->handle(new Request(['filename' => 'report.txt', 'mime_type' => 'text/plain', 'content' => 'v2 content']));

    $chunk = DocumentEmbedding::sole();
    expect($chunk->chunk_text)->toBe('v2 content');
});

it('does not index a non-text export (e.g. an image)', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $run = $session->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);
    $tool = new ExportArtifactTool($agent, $session, $run, app(StoreArtifactAction::class));

    $tool->handle(new Request([
        'filename' => 'image.png', 'mime_type' => 'image/png',
        'content' => base64_encode('binary-data'), 'is_base64' => true,
    ]));

    expect(DocumentEmbedding::count())->toBe(0);
});

it('returns an error when required fields are missing', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $run = $session->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);
    $tool = new ExportArtifactTool($agent, $session, $run, app(StoreArtifactAction::class));

    $result = json_decode($tool->handle(new Request(['filename' => 'report.txt'])), true);

    expect($result['error'])->not->toBeNull();
    expect(Artifact::count())->toBe(0);
});

it('builds a real PDF when asked for format pdf, whatever filename and type the model gave', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $run = $session->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);

    $tool = new ExportArtifactTool($agent, $session, $run, app(StoreArtifactAction::class));
    $result = json_decode($tool->handle(new Request([
        'filename' => 'action-items.md',
        'mime_type' => 'text/markdown',
        'content' => "# Action items\n\n- Ravi: Q4 budget",
        'format' => 'pdf',
    ])), true);

    $artifact = Artifact::sole();
    expect($result['filename'])->toBe('action-items.pdf');
    expect($artifact->mime_type)->toBe('application/pdf');
    expect(Storage::disk('local')->get($artifact->path))->toStartWith('%PDF-');
});

it('rejects a format it cannot build', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $run = $session->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);

    $tool = new ExportArtifactTool($agent, $session, $run, app(StoreArtifactAction::class));
    $result = json_decode($tool->handle(new Request([
        'filename' => 'deck',
        'mime_type' => 'x',
        'content' => 'slides',
        'format' => 'pptx',
    ])), true);

    expect($result['error'])->toBe('format must be one of: pdf, xlsx, docx.');
    expect(Artifact::count())->toBe(0);
});
