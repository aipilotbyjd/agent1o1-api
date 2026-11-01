<?php

use App\Enums\Agents\AgentMessageRole;
use App\Enums\Artifacts\ArtifactGeneralAccess;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Artifacts\Artifact;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create(['name' => 'Designer']);
    $this->session = AgentSession::factory()->forAgent($this->agent)->create(['title' => 'Logo ideas']);
    $this->url = "/api/v1/workspaces/{$this->workspace->id}/library";

    $this->file = function (string $filename, string $mime, array $overrides = []): Artifact {
        Storage::disk('local')->put("artifacts/{$filename}", 'bytes');

        return Artifact::create([
            'workspace_id' => $this->workspace->id,
            'agent_id' => $this->agent->id,
            'agent_session_id' => $this->session->id,
            'created_by' => $this->owner->id,
            'group_id' => (string) Str::uuid(),
            'version' => 1,
            'filename' => $filename,
            'mime_type' => $mime,
            'size' => 5,
            'disk' => 'local',
            'path' => "artifacts/{$filename}",
            ...$overrides,
        ]);
    };

    Passport::actingAs($this->owner);
});

it('lists files from chats, newest first, with their agent and chat', function () {
    ($this->file)('logo.png', 'image/png', ['created_at' => now()->subHour()]);
    ($this->file)('brief.pdf', 'application/pdf');

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.filename', 'brief.pdf')
        ->assertJsonPath('data.0.kind', 'file')
        ->assertJsonPath('data.1.kind', 'image')
        ->assertJsonPath('data.1.agent.name', 'Designer')
        ->assertJsonPath('data.1.chat.title', 'Logo ideas');
});

it('leaves out files that never went through a chat', function () {
    ($this->file)('manual-upload.png', 'image/png', ['agent_session_id' => null]);

    $this->getJson($this->url)->assertOk()->assertJsonCount(0, 'data');
});

it('filters to images or to other files', function () {
    ($this->file)('logo.png', 'image/png');
    ($this->file)('brief.pdf', 'application/pdf');

    $this->getJson("{$this->url}?type=image")->assertJsonCount(1, 'data')->assertJsonPath('data.0.filename', 'logo.png');
    $this->getJson("{$this->url}?type=file")->assertJsonCount(1, 'data')->assertJsonPath('data.0.filename', 'brief.pdf');
});

it('tells uploads from files the agent made', function () {
    $userMessage = $this->session->messages()->create(['role' => AgentMessageRole::User, 'content' => 'here']);
    ($this->file)('mine.png', 'image/png', ['agent_message_id' => $userMessage->id]);

    $this->getJson($this->url)->assertJsonPath('data.0.source', 'uploaded');
});

it('shows a member only files they may open, as Artifacts does', function () {
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    ($this->file)('secret.png', 'image/png', ['general_access' => ArtifactGeneralAccess::Restricted]);
    ($this->file)('open.png', 'image/png', ['general_access' => ArtifactGeneralAccess::Organization]);

    Passport::actingAs($member);

    $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.filename', 'open.png');
});

it('serves a file through its signed view link and downloads it', function () {
    $image = ($this->file)('logo.png', 'image/png');

    $viewUrl = $this->getJson($this->url)->json('data.0.view_url');
    $this->get($viewUrl)->assertOk()->assertHeader('Content-Security-Policy', 'sandbox');
    $this->get(URL::route('library.view', ['artifact' => $image->id]))->assertForbidden();

    $this->get("{$this->url}/{$image->id}/download")->assertOk();
});

it('includes files sent to the workflow builder, as uploads without an agent', function () {
    $builderSession = WorkflowBuilderSession::factory()->forWorkspace($this->workspace, $this->owner)->create();
    $message = $builderSession->messages()->create(['role' => 'user', 'content' => 'Build this.']);
    $file = ($this->file)('spec.txt', 'text/plain', [
        'agent_id' => null,
        'agent_session_id' => null,
        'workflow_builder_message_id' => $message->id,
    ]);

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.filename', 'spec.txt')
        ->assertJsonPath('data.0.source', 'uploaded')
        ->assertJsonPath('data.0.agent', null)
        ->assertJsonPath('data.0.chat', null);

    $this->get("{$this->url}/{$file->id}/download")->assertOk();
});
