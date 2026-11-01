<?php

use App\Actions\Agents\CreateAgentSessionAction;
use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Agents\AgentMessageRole;
use App\Enums\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Base64Document;
use Laravel\Ai\Files\StoredImage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Passport\Passport;

beforeEach(function () {
    Storage::fake(config('artifacts.disk'));
});

/**
 * @return array{0: Workspace, 1: User, 2: Agent, 3: AgentSession, 4: string}
 */
function chatWithAttachments(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    Passport::actingAs($owner);

    return [$workspace, $owner, $agent, $session, "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions/{$session->id}/messages"];
}

function pngAttachment(string $name = 'chart.png'): UploadedFile
{
    // A 1x1 transparent PNG.
    return UploadedFile::fake()->createWithContent($name, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
    ));
}

it('stores attachments on the user message and sends them to the model', function () {
    WorkspaceAgent::fake(['Looks like a chart.']);
    [, $owner, $agent, $session, $url] = chatWithAttachments();

    $response = $this->postJson($url, [
        'message' => 'What do these show?',
        'attachments' => [pngAttachment(), UploadedFile::fake()->createWithContent('notes.txt', 'Q3 revenue is up.')],
    ]);

    $response->assertOk()->assertJsonPath('data.message.content', 'Looks like a chart.');

    $userMessage = $session->messages()->where('role', AgentMessageRole::User)->sole();
    $attachments = $userMessage->attachments()->orderBy('filename')->get();

    expect($attachments->pluck('filename')->all())->toBe(['chart.png', 'notes.txt']);
    expect($attachments->pluck('mime_type')->all())->toBe(['image/png', 'text/plain']);
    expect($attachments->pluck('agent_session_id')->unique()->all())->toBe([$session->id]);
    expect($attachments->pluck('agent_id')->unique()->all())->toBe([$agent->id]);
    expect($attachments->pluck('created_by')->unique()->all())->toBe([$owner->id]);
    expect($attachments->pluck('group_id')->unique())->toHaveCount(2);
    $attachments->each(fn ($artifact) => Storage::disk($artifact->disk)->assertExists($artifact->path));

    $run = Run::where('runnable_id', $session->id)->sole();
    expect($run->input['attachment_ids'])->toEqualCanonicalizing($attachments->pluck('id')->all());

    WorkspaceAgent::assertPrompted(function ($prompt): bool {
        $sent = $prompt->attachments->values();

        return $prompt->prompt === 'What do these show?'
            && $sent->count() === 2
            && $sent[0] instanceof StoredImage
            && $sent[0]->name() === 'chart.png'
            && $sent[1] instanceof Base64Document
            && $sent[1]->name() === 'notes.txt'
            && base64_decode($sent[1]->base64) === 'Q3 revenue is up.';
    });
});

it('keeps attachments out of the agent\'s searchable knowledge', function () {
    WorkspaceAgent::fake(['ok']);
    [, , , , $url] = chatWithAttachments();

    $this->postJson($url, [
        'message' => 'Read this.',
        'attachments' => [UploadedFile::fake()->createWithContent('private.txt', 'Salary: 100k')],
    ])->assertOk();

    expect(DocumentEmbedding::count())->toBe(0);
});

it('replays earlier attachments on later turns', function () {
    WorkspaceAgent::fake(['first', 'second']);
    [, , , , $url] = chatWithAttachments();

    $this->postJson($url, ['message' => 'Here is the chart.', 'attachments' => [pngAttachment()]])->assertOk();
    $this->postJson($url, ['message' => 'What colour was it?'])->assertOk();

    WorkspaceAgent::assertPrompted(function ($prompt): bool {
        if ($prompt->prompt !== 'What colour was it?' || $prompt->attachments->isNotEmpty()) {
            return false;
        }

        $history = collect($prompt->agent->messages());
        $earlier = $history->first();

        return $earlier instanceof UserMessage
            && $earlier->content === 'Here is the chart.'
            && $earlier->attachments->count() === 1
            && $earlier->attachments->first() instanceof StoredImage;
    });
});

it('returns attachments with the transcript', function () {
    WorkspaceAgent::fake(['ok']);
    [$workspace, , $agent, $session, $url] = chatWithAttachments();

    $this->postJson($url, ['message' => 'See attached.', 'attachments' => [pngAttachment()]])->assertOk();

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.0.content', 'See attached.')
        ->assertJsonPath('data.0.attachments.0.filename', 'chart.png')
        ->assertJsonPath('data.0.attachments.0.mime_type', 'image/png')
        ->assertJsonPath('data.1.attachments', []);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions/{$session->id}")
        ->assertOk()
        ->assertJsonPath('data.session.messages.0.attachments.0.filename', 'chart.png');
});

it('hands attachments to the queued turn on the Reverb endpoint', function () {
    WorkspaceAgent::fake(['Streamed.']);
    [, , , $session, $url] = chatWithAttachments();

    $this->post(preg_replace('#/messages$#', '/turns', $url), [
        'message' => 'Describe this.',
        'attachments' => [pngAttachment()],
    ], ['Accept' => 'application/json'])->assertAccepted();

    expect($session->runs()->sole()->status)->toBe(RunStatus::Completed);

    $userMessage = $session->messages()->where('role', AgentMessageRole::User)->sole();
    expect($userMessage->attachments()->sole()->filename)->toBe('chart.png');

    WorkspaceAgent::assertPrompted(fn ($prompt) => $prompt->attachments->count() === 1);
});

it('rejects an unsupported file type', function () {
    WorkspaceAgent::fake(['ok']);
    [, , , $session, $url] = chatWithAttachments();

    $this->postJson($url, [
        'message' => 'Open this.',
        'attachments' => [UploadedFile::fake()->createWithContent('tool.exe', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff")],
    ])->assertUnprocessable()->assertJsonValidationErrors('attachments.0');

    expect($session->messages()->count())->toBe(0);
    WorkspaceAgent::assertNeverPrompted();
});

it('rejects more attachments than the configured limit', function () {
    config(['artifacts.message_attachments.max_files' => 1]);
    WorkspaceAgent::fake(['ok']);
    [, , , , $url] = chatWithAttachments();

    $this->postJson($url, [
        'message' => 'Two files.',
        'attachments' => [pngAttachment('a.png'), pngAttachment('b.png')],
    ])->assertUnprocessable()->assertJsonValidationErrors('attachments');
});
