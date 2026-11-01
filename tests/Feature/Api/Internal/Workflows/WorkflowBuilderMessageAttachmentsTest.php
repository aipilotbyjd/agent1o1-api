<?php

use App\Ai\Agents\WorkflowBuilderAgent;
use App\Ai\Agents\WorkflowBuilderTitleAgent;
use App\Models\Agents\DocumentEmbedding;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\StoredImage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Passport\Passport;

beforeEach(function () {
    Storage::fake(config('artifacts.disk'));
    WorkflowBuilderTitleAgent::fake([]);
});

/**
 * @return array{0: Workspace, 1: User, 2: WorkflowBuilderSession, 3: string}
 */
function builderChatWithAttachments(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create(['title' => 'Signup Alerts']);

    Passport::actingAs($owner);

    return [$workspace, $owner, $session, "/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages"];
}

function builderPngAttachment(string $name = 'flow.png'): UploadedFile
{
    // A 1x1 transparent PNG.
    return UploadedFile::fake()->createWithContent($name, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
    ));
}

it('stores attachments on the user message and sends them to the model', function () {
    WorkflowBuilderAgent::fake(['Built it from your sketch.']);
    [, $owner, $session, $url] = builderChatWithAttachments();

    $this->postJson($url, [
        'message' => 'Build this.',
        'attachments' => [builderPngAttachment(), UploadedFile::fake()->createWithContent('spec.txt', 'Email me on signup.')],
    ])->assertAccepted();

    $userMessage = $session->messages()->where('role', 'user')->sole();
    $attachments = $userMessage->attachments()->orderBy('filename')->get();

    expect($attachments->pluck('filename')->all())->toBe(['flow.png', 'spec.txt']);
    expect($attachments->pluck('mime_type')->all())->toBe(['image/png', 'text/plain']);
    expect($attachments->pluck('created_by')->unique()->all())->toBe([$owner->id]);
    expect($attachments->pluck('agent_id')->unique()->all())->toBe([null]);
    expect($attachments->pluck('group_id')->unique())->toHaveCount(2);
    $attachments->each(fn ($artifact) => Storage::disk($artifact->disk)->assertExists($artifact->path));

    // Text files are written into the message: the builder's providers
    // only accept images as attachments.
    WorkflowBuilderAgent::assertPrompted(function ($prompt): bool {
        $sent = $prompt->attachments->values();

        return $prompt->prompt === "Build this.\n\n<attached_file name=\"spec.txt\">\nEmail me on signup.\n</attached_file>"
            && $sent->count() === 1
            && $sent[0] instanceof StoredImage
            && $sent[0]->name() === 'flow.png';
    });
});

it('keeps attachments out of searchable knowledge', function () {
    WorkflowBuilderAgent::fake(['ok']);
    [, , , $url] = builderChatWithAttachments();

    $this->postJson($url, [
        'message' => 'Read this.',
        'attachments' => [UploadedFile::fake()->createWithContent('private.txt', 'Salary: 100k')],
    ])->assertAccepted();

    expect(DocumentEmbedding::count())->toBe(0);
});

it('replays earlier attachments on later turns', function () {
    WorkflowBuilderAgent::fake(['first', 'second']);
    [, , , $url] = builderChatWithAttachments();

    $this->postJson($url, ['message' => 'Here is the sketch.', 'attachments' => [builderPngAttachment()]])->assertAccepted();
    $this->postJson($url, ['message' => 'Add an error branch.'])->assertAccepted();

    WorkflowBuilderAgent::assertPrompted(function ($prompt): bool {
        if ($prompt->prompt !== 'Add an error branch.' || $prompt->attachments->isNotEmpty()) {
            return false;
        }

        $earlier = collect($prompt->agent->messages())->first();

        return $earlier instanceof UserMessage
            && $earlier->content === 'Here is the sketch.'
            && $earlier->attachments->count() === 1
            && $earlier->attachments->first() instanceof StoredImage;
    });
});

it('returns attachments with the transcript and the session', function () {
    WorkflowBuilderAgent::fake(['ok']);
    [$workspace, , $session, $url] = builderChatWithAttachments();

    $this->postJson($url, ['message' => 'See attached.', 'attachments' => [builderPngAttachment()]])->assertAccepted();

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.messages.0.content', 'See attached.')
        ->assertJsonPath('data.messages.0.attachments.0.filename', 'flow.png')
        ->assertJsonPath('data.messages.1.attachments', []);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}")
        ->assertOk()
        ->assertJsonPath('data.session.messages.0.attachments.0.filename', 'flow.png');
});

it('rejects a file type the model cannot read', function () {
    [, , $session, $url] = builderChatWithAttachments();

    $this->postJson($url, [
        'message' => 'Build this.',
        'attachments' => [UploadedFile::fake()->create('tool.zip', 10, 'application/zip')],
    ])->assertUnprocessable()->assertJsonValidationErrors('attachments.0');

    expect($session->messages()->count())->toBe(0);
});

it('rejects an attachment that is not a file', function () {
    [, , , $url] = builderChatWithAttachments();

    $this->postJson($url, ['message' => 'Build this.', 'attachments' => [['name' => 'x']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attachments.0');
});

it('refuses PDFs, which the builder\'s model cannot read', function () {
    [, , $session, $url] = builderChatWithAttachments();

    $this->postJson($url, [
        'message' => 'Build this.',
        'attachments' => [UploadedFile::fake()->createWithContent('spec.pdf', "%PDF-1.4\n%%EOF")],
    ])->assertUnprocessable()->assertJsonValidationErrors('attachments.0');

    expect($session->messages()->count())->toBe(0);
});
