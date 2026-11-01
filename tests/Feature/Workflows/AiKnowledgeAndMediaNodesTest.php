<?php

use App\Ai\Agents\AdHocPromptAgent;
use App\Enums\NodeRunStatus;
use App\Models\Agents\Agent;
use App\Models\Agents\DocumentEmbedding;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Audio;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Image;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Transcription;

beforeEach(function () {
    // Nothing here may reach an outside service for real: an unfaked call fails the test.
    Http::preventStrayRequests();
});

function aiWorkspace(): Workspace
{
    return app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
}

function knowledgeChunk(Workspace $workspace, string $source, string $text, array $embedding, array $extra = []): DocumentEmbedding
{
    return DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => $source, 'chunk_text' => $text, 'embedding' => $embedding, ...$extra]);
}

/* ---------------------------------------------------------------- knowledge */

it('searches the shared knowledge base and wraps the passages as untrusted context', function () {
    $workspace = aiWorkspace();
    Embeddings::fake([[[1.0, 0.0, 0.0]]]);
    knowledgeChunk($workspace, 'Refund policy', 'Refunds are issued within 14 days.', [0.9, 0.1, 0.0], ['collection' => 'policies']);
    knowledgeChunk($workspace, 'Shipping', 'We ship worldwide.', [0.0, 1.0, 0.0]);

    $nodeRun = runNodeStep('knowledge_search', ['query' => 'refunds', 'max_results' => 3], workspace: $workspace);

    expect($nodeRun->status)->toBe(NodeRunStatus::Completed)
        ->and($nodeRun->output)->toMatchArray(['count' => 1, 'found' => true, 'result' => 'found'])
        ->and($nodeRun->output['results'][0])->toMatchArray(['source' => 'Refund policy', 'collection' => 'policies'])
        ->and($nodeRun->output['context'])->toStartWith('<untrusted_content')
        ->and($nodeRun->output['context'])->toContain('Refunds are issued within 14 days.');
});

it('can limit a knowledge search to one collection', function () {
    $workspace = aiWorkspace();
    Embeddings::fake([[[1.0, 0.0]]]);
    knowledgeChunk($workspace, 'a', 'alpha text', [1.0, 0.0], ['collection' => 'one']);
    knowledgeChunk($workspace, 'b', 'beta text', [1.0, 0.0], ['collection' => 'two']);

    $nodeRun = runNodeStep('knowledge_search', ['query' => 'text', 'collection' => 'two'], workspace: $workspace);

    expect(array_column($nodeRun->output['results'], 'source'))->toBe(['b']);
});

it('never shows a workflow a member’s private knowledge or another workspace’s', function () {
    $workspace = aiWorkspace();
    $other = aiWorkspace();
    Embeddings::fake([[[1.0, 0.0]]]);
    knowledgeChunk($workspace, 'shared', 'shared note', [1.0, 0.0]);
    knowledgeChunk($workspace, 'private', 'private note', [1.0, 0.0], ['owner_id' => $workspace->owner_id]);
    knowledgeChunk($other, 'foreign', 'foreign note', [1.0, 0.0]);

    $nodeRun = runNodeStep('knowledge_search', ['query' => 'note'], workspace: $workspace);

    expect(array_column($nodeRun->output['results'], 'source'))->toBe(['shared']);
});

it('reports none when nothing matches', function () {
    $workspace = aiWorkspace();
    Embeddings::fake([[[1.0, 0.0]]]);

    $nodeRun = runNodeStep('knowledge_search', ['query' => 'anything'], workspace: $workspace);

    expect($nodeRun->output)->toMatchArray(['count' => 0, 'found' => false, 'result' => 'none', 'context' => '']);
});

it('saves text to the knowledge base and replaces an earlier version of the same name', function () {
    $workspace = aiWorkspace();
    Embeddings::fake();

    runNodeStep('knowledge_save', ['text' => 'First version.', 'source' => 'Policy', 'collection' => 'docs'], workspace: $workspace);
    $nodeRun = runNodeStep('knowledge_save', ['text' => 'Second version.', 'source' => 'Policy', 'collection' => 'docs'], workspace: $workspace);

    expect($nodeRun->output)->toMatchArray(['source' => 'Policy', 'collection' => 'docs'])
        ->and(DocumentEmbedding::query()->where('workspace_id', $workspace->id)->where('source', 'Policy')->pluck('chunk_text')->all())->toBe(['Second version.']);
});

it('refuses to save text with no name', function () {
    Embeddings::fake();

    expect(fn () => executeNode('knowledge_save', ['text' => 'x', 'source' => ' '], aiWorkspace()))->toThrow(InvalidArgumentException::class, 'name')
        ->and(fn () => executeNode('knowledge_save', ['text' => '  ', 'source' => 'Policy'], aiWorkspace()))->toThrow(InvalidArgumentException::class);
});

/* --------------------------------------------------------------- ai_answer */

it('answers from provided text and names the source it used', function () {
    AdHocPromptAgent::fake(['{"answered": true, "answer": "Within 14 days.", "used": [1]}']);

    $nodeRun = runNodeStep('ai_answer', [
        'question' => 'How long do refunds take?',
        'context' => 'Refunds are issued within 14 days.',
        'use_knowledge_base' => false,
        'provider' => 'openai',
        'model' => 'gpt-4o',
    ]);

    expect($nodeRun->output)->toMatchArray(['answer' => 'Within 14 days.', 'found' => true, 'result' => 'answered'])
        ->and($nodeRun->output['sources'][0]['label'])->toBe('Provided text');

    AdHocPromptAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, '<untrusted_content') && str_contains($prompt->prompt, 'Refunds are issued'));
});

it('says so when the sources do not contain the answer', function () {
    AdHocPromptAgent::fake(['{"answered": false, "answer": "The policy does not mention exchanges.", "used": []}']);

    $nodeRun = runNodeStep('ai_answer', ['question' => 'Can I exchange?', 'context' => 'Refunds within 14 days.', 'use_knowledge_base' => false, 'provider' => 'openai', 'model' => 'gpt-4o']);

    expect($nodeRun->output)->toMatchArray(['found' => false, 'result' => 'not_found', 'answer' => 'The policy does not mention exchanges.', 'sources' => []]);
});

it('does not ask the model at all when there is nothing to read', function () {
    AdHocPromptAgent::fake(['should never be used']);
    Embeddings::fake([[[1.0, 0.0]]]);

    $nodeRun = runNodeStep('ai_answer', ['question' => 'Anything?', 'provider' => 'openai', 'model' => 'gpt-4o'], workspace: aiWorkspace());

    expect($nodeRun->output)->toMatchArray(['found' => false, 'result' => 'not_found']);

    AdHocPromptAgent::assertNeverPrompted();
});

it('answers from the knowledge base and ignores source numbers the model made up', function () {
    AdHocPromptAgent::fake(['{"answered": true, "answer": "Yes.", "used": [1, 9, "x"]}']);
    $workspace = aiWorkspace();
    Embeddings::fake([[[1.0, 0.0]]]);
    knowledgeChunk($workspace, 'FAQ', 'Yes, we ship to Canada.', [1.0, 0.0]);

    $nodeRun = runNodeStep('ai_answer', ['question' => 'Ship to Canada?', 'provider' => 'openai', 'model' => 'gpt-4o'], workspace: $workspace);

    expect(array_column($nodeRun->output['sources'], 'label'))->toBe(['FAQ']);
});

/* -------------------------------------------------------------- ai_read_file */

it('reads a PDF and extracts the fields asked for', function () {
    AdHocPromptAgent::fake(['{"vendor": "Initech", "total": 120.5, "po": ""}']);

    $nodeRun = runNodeStep('ai_read_file', [
        'file_base64' => base64_encode("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
        'mime_type' => 'application/pdf',
        'fields' => ['vendor' => 'the vendor', 'total' => 'the total as a number', 'po' => 'the PO number'],
        'provider' => 'openai',
        'model' => 'gpt-4o',
    ]);

    expect($nodeRun->output)->toMatchArray(['vendor' => 'Initech', 'total' => 120.5, 'po' => null, 'data' => ['vendor' => 'Initech', 'total' => 120.5, 'po' => null]]);

    AdHocPromptAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->attachments->count() === 1);
});

it('reads a file handed on from an earlier step and downloads one from a link through the outbound guard', function () {
    AdHocPromptAgent::fake(['A receipt.', 'A receipt.']);
    Http::fake(['203.0.113.7/*' => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='), 200, ['Content-Type' => 'image/png'])]);

    $fromStep = runNodeStep('ai_read_file', ['file' => ['content_base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'mime_type' => 'image/png', 'filename' => 'r.png'], 'task' => 'What is this?', 'provider' => 'openai', 'model' => 'gpt-4o']);
    $fromLink = runNodeStep('ai_read_file', ['file_url' => 'http://203.0.113.7/receipt.png', 'task' => 'What is this?', 'provider' => 'openai', 'model' => 'gpt-4o']);

    expect($fromStep->output['text'])->toBe('A receipt.')
        ->and($fromLink->output['text'])->toBe('A receipt.');
});

it('refuses a file type it cannot read, an empty request and a link to a private address', function () {
    AdHocPromptAgent::fake(['x']);

    $zip = runNodeStep('ai_read_file', ['file_base64' => base64_encode('PK'), 'mime_type' => 'application/zip', 'task' => 'x', 'provider' => 'openai', 'model' => 'gpt-4o']);
    $noTask = runNodeStep('ai_read_file', ['file_base64' => base64_encode('x'), 'mime_type' => 'image/png', 'provider' => 'openai', 'model' => 'gpt-4o']);
    $private = runNodeStep('ai_read_file', ['file_url' => 'http://127.0.0.1/secret.pdf', 'task' => 'x', 'provider' => 'openai', 'model' => 'gpt-4o']);

    expect($zip->status)->toBe(NodeRunStatus::Failed)
        ->and($noTask->status)->toBe(NodeRunStatus::Failed)
        ->and($private->status)->toBe(NodeRunStatus::Failed);
});

/* ------------------------------------------------------------- moderate etc. */

it('flags text at or above the chosen severity', function (string $severity, string $flagFrom, bool $flagged) {
    AdHocPromptAgent::fake([json_encode(['severity' => $severity, 'categories' => ['spam'], 'reason' => 'Looks like spam.'])]);

    $nodeRun = runNodeStep('ai_moderate', ['text' => 'Buy now!!!', 'flag_from' => $flagFrom, 'provider' => 'openai', 'model' => 'gpt-4o']);

    expect($nodeRun->output)->toMatchArray(['flagged' => $flagged, 'result' => $flagged ? 'flagged' : 'clean', 'severity' => $severity, 'categories' => ['spam']]);
})->with([
    'medium flagged by default' => ['medium', 'medium', true],
    'low passes at medium' => ['low', 'medium', false],
    'low flagged from low' => ['low', 'low', true],
    'medium passes at high' => ['medium', 'high', false],
    'high flagged at high' => ['high', 'high', true],
    'none is clean' => ['none', 'low', false],
]);

it('treats a severity it does not recognise as medium rather than letting it through', function () {
    AdHocPromptAgent::fake(['{"severity": "catastrophic", "categories": [], "reason": "?"}']);

    expect(runNodeStep('ai_moderate', ['text' => 'x', 'provider' => 'openai', 'model' => 'gpt-4o'])->output)->toMatchArray(['severity' => 'medium', 'flagged' => true]);
});

it('compares two versions and reports the changes', function () {
    AdHocPromptAgent::fake([json_encode([
        'summary' => 'The fee went up.',
        'significance' => 'significant',
        'changes' => [['type' => 'changed', 'before' => '$10', 'after' => '$12', 'importance' => 'high'], ['type' => 'bogus', 'importance' => 'huge']],
    ])]);

    $nodeRun = runNodeStep('ai_compare', ['original' => 'The fee is $10.', 'revised' => 'The fee is $12.', 'provider' => 'openai', 'model' => 'gpt-4o']);

    expect($nodeRun->output)->toMatchArray(['significance' => 'significant', 'result' => 'significant'])
        ->and($nodeRun->output['changes'][0])->toMatchArray(['type' => 'changed', 'before' => '$10', 'after' => '$12', 'importance' => 'high'])
        ->and($nodeRun->output['changes'][1])->toMatchArray(['type' => 'changed', 'importance' => 'medium']);
});

it('answers identical text without asking the model', function () {
    AdHocPromptAgent::fake(['unused']);

    $nodeRun = runNodeStep('ai_compare', ['original' => "Same  text\n", 'revised' => 'Same text', 'provider' => 'openai', 'model' => 'gpt-4o']);

    expect($nodeRun->output)->toMatchArray(['result' => 'identical', 'changes' => []]);

    AdHocPromptAgent::assertNeverPrompted();
});

it('routes a task to one of the workspace agents', function () {
    $workspace = aiWorkspace();
    $billing = Agent::factory()->forWorkspace($workspace)->create(['name' => 'Billing', 'description' => 'Handles invoices and refunds']);
    Agent::factory()->forWorkspace($workspace)->create(['name' => 'Support', 'description' => 'Answers product questions']);
    Agent::factory()->forWorkspace(aiWorkspace())->create(['name' => 'Elsewhere', 'description' => 'Another workspace']);
    AdHocPromptAgent::fake(['{"choice": 1, "confidence": 0.9, "reason": "It is about an invoice."}']);

    $nodeRun = runNodeStep('ai_route_to_agent', ['task' => 'I was double charged', 'provider' => 'openai', 'model' => 'gpt-4o'], workspace: $workspace);

    expect($nodeRun->output)->toMatchArray(['agent_id' => $billing->id, 'agent_name' => 'Billing', 'confidence' => 0.9]);

    AdHocPromptAgent::assertPrompted(fn (AgentPrompt $prompt): bool => ! str_contains($prompt->prompt, 'Elsewhere'));
});

it('fails routing when there are no agents or the model picks a number that is not on the list', function () {
    AdHocPromptAgent::fake(['{"choice": 5}']);
    $none = runNodeStep('ai_route_to_agent', ['task' => 'x', 'provider' => 'openai', 'model' => 'gpt-4o'], workspace: aiWorkspace());

    $workspace = aiWorkspace();
    Agent::factory()->forWorkspace($workspace)->create(['name' => 'Only']);
    AdHocPromptAgent::fake(['{"choice": 5}']);
    $outOfRange = runNodeStep('ai_route_to_agent', ['task' => 'x', 'provider' => 'openai', 'model' => 'gpt-4o'], workspace: $workspace);

    expect($none->status)->toBe(NodeRunStatus::Failed)
        ->and($outOfRange->status)->toBe(NodeRunStatus::Failed);
});

/* -------------------------------------------------------------------- media */

it('transcribes a recording', function () {
    Transcription::fake(['Hello team, here is the update.']);

    $nodeRun = runNodeStep('ai_transcribe', ['file_base64' => base64_encode('RIFF....WAVEfmt '), 'mime_type' => 'audio/wav', 'language' => 'en']);

    expect($nodeRun->status)->toBe(NodeRunStatus::Completed)
        ->and($nodeRun->output['text'])->toBe('Hello team, here is the update.');
});

it('refuses to transcribe something that is not audio', function () {
    Transcription::fake();

    expect(runNodeStep('ai_transcribe', ['file_base64' => base64_encode('%PDF-1.4'), 'mime_type' => 'application/pdf'])->status)->toBe(NodeRunStatus::Failed);
});

it('turns text into an audio file other steps can attach', function () {
    Audio::fake();

    $nodeRun = runNodeStep('ai_speech', ['text' => 'Your report is ready.', 'voice' => 'female', 'style' => 'calm']);

    expect($nodeRun->status)->toBe(NodeRunStatus::Completed)
        ->and($nodeRun->output['file'])->toHaveKeys(['filename', 'mime_type', 'size', 'content_base64'])
        ->and($nodeRun->output['file']['filename'])->toStartWith('speech.')
        ->and($nodeRun->output['size'])->toBe($nodeRun->output['file']['size']);

    Audio::assertGenerated(fn ($prompt): bool => str_contains((string) ($prompt->text ?? ''), 'Your report is ready.'));
});

it('refuses text too long to speak', function () {
    Audio::fake();

    expect(fn () => executeNode('ai_speech', ['text' => str_repeat('a', 4001)]))->toThrow(InvalidArgumentException::class, 'too long');

    Audio::assertNothingGenerated();
});

it('creates an image and returns it as a file', function () {
    Image::fake();

    $nodeRun = runNodeStep('ai_image', ['prompt' => 'A lighthouse at dusk', 'shape' => 'landscape', 'quality' => 'high']);

    expect($nodeRun->status)->toBe(NodeRunStatus::Completed)
        ->and($nodeRun->output['file'])->toHaveKeys(['filename', 'mime_type', 'size', 'content_base64'])
        ->and($nodeRun->output['file']['filename'])->toStartWith('image.');

    Image::assertGenerated(fn ($prompt): bool => str_contains($prompt->prompt, 'lighthouse'));
});

it('refuses an empty or oversized image description', function () {
    Image::fake();

    expect(fn () => executeNode('ai_image', ['prompt' => ' ']))->toThrow(InvalidArgumentException::class, 'is empty')
        ->and(fn () => executeNode('ai_image', ['prompt' => str_repeat('a', 4001)]))->toThrow(InvalidArgumentException::class, 'too long');

    Image::assertNothingGenerated();
});
