<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GoogleDocs\GoogleDocsAppendTextNode;
use App\Nodes\Integrations\GoogleDocs\GoogleDocsCreateDocumentNode;
use App\Nodes\Integrations\GoogleDocs\GoogleDocsGetDocumentNode;
use App\Nodes\Integrations\GoogleDocs\GoogleDocsReadTextNode;
use App\Nodes\Integrations\GoogleDocs\GoogleDocsReplaceTextNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

it('registers every google docs node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $types = [
        'google_docs_create_document' => GoogleDocsCreateDocumentNode::class,
        'google_docs_get_document' => GoogleDocsGetDocumentNode::class,
        'google_docs_append_text' => GoogleDocsAppendTextNode::class,
    ];

    foreach ($types as $type => $class) {
        expect($registry->has($type))->toBeTrue();
        $node = $registry->resolve($type);
        expect($node)->toBeInstanceOf($class);
        expect($node->category())->toBe('google_docs');
    }
});

it('creates a document with a title', function () {
    Http::fake(['docs.googleapis.com/*' => Http::response(['documentId' => 'd1', 'title' => 'My Doc'])]);

    $node = new GoogleDocsCreateDocumentNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test', 'title' => 'My Doc'], []);

    expect($output)->toBe(['documentId' => 'd1', 'title' => 'My Doc']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/documents') && $request['title'] === 'My Doc');
});

it('gets a document by id', function () {
    Http::fake(['docs.googleapis.com/*' => Http::response(['documentId' => 'd1'])]);

    $node = new GoogleDocsGetDocumentNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test', 'document_id' => 'd1'], []);

    expect($output)->toBe(['documentId' => 'd1']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/documents/d1'));
});

it('appends text via a batchUpdate insertText request', function () {
    Http::fake(['docs.googleapis.com/*' => Http::response(['documentId' => 'd1'])]);

    $node = new GoogleDocsAppendTextNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'ya29-test', 'document_id' => 'd1', 'text' => 'Hello'], []);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/v1/documents/d1:batchUpdate')
            && $request['requests'][0]['insertText']['text'] === 'Hello';
    });
});

it('throws when docs answers a non-2xx status', function () {
    Http::fake(['docs.googleapis.com/*' => Http::response(['error' => ['message' => 'Requested entity was not found.']], 404)]);

    $node = new GoogleDocsGetDocumentNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['access_token' => 'ya29-test', 'document_id' => 'missing'], []))
        ->toThrow(RuntimeException::class, 'Requested entity was not found.');
});

it('throws when access_token is missing', function () {
    $node = new GoogleDocsGetDocumentNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['document_id' => 'd1'], []))->toThrow(RuntimeException::class, 'access_token');
});

it('registers the read text and replace text nodes', function () {
    expect(app(NodeRegistry::class)->has('google_docs_read_text'))->toBeTrue()
        ->and(app(NodeRegistry::class)->has('google_docs_replace_text'))->toBeTrue();
});

it('creates a document that already holds its starting text', function () {
    Http::fake([
        'docs.googleapis.com/v1/documents' => Http::response(['documentId' => 'doc1', 'title' => 'Notes']),
        'docs.googleapis.com/*' => Http::response(['documentId' => 'doc1']),
    ]);

    $output = (new GoogleDocsCreateDocumentNode)->execute(Run::factory()->create(), ['access_token' => 'ya29-test', 'title' => 'Notes', 'content' => 'Agenda'], []);

    expect($output['documentId'])->toBe('doc1');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/documents/doc1:batchUpdate')
        && $request['requests'][0]['insertText'] === ['location' => ['index' => 1], 'text' => 'Agenda']);
});

it('creates an empty document with a single request', function () {
    Http::fake(['docs.googleapis.com/*' => Http::response(['documentId' => 'doc2'])]);

    (new GoogleDocsCreateDocumentNode)->execute(Run::factory()->create(), ['access_token' => 'ya29-test', 'title' => 'Blank'], []);

    Http::assertSentCount(1);
});

it('reads a document as plain text with its tables flattened', function () {
    Http::fake(['docs.googleapis.com/*' => Http::response([
        'documentId' => 'doc1',
        'title' => 'Plan',
        'body' => ['content' => [
            ['paragraph' => ['elements' => [['textRun' => ['content' => "Intro\n"]]]]],
            ['table' => ['tableRows' => [
                ['tableCells' => [
                    ['content' => [['paragraph' => ['elements' => [['textRun' => ['content' => "Name\n"]]]]]]],
                    ['content' => [['paragraph' => ['elements' => [['textRun' => ['content' => "Role\n"]]]]]]],
                ]],
            ]]],
            ['sectionBreak' => []],
            ['paragraph' => ['elements' => [['textRun' => ['content' => "End\n"]], ['inlineObjectElement' => []]]]],
        ]],
    ])]);

    $output = (new GoogleDocsReadTextNode)->execute(Run::factory()->create(), ['access_token' => 'ya29-test', 'document_id' => 'doc1'], []);

    expect($output)->toBe(['document_id' => 'doc1', 'title' => 'Plan', 'revision_id' => null, 'text' => "Intro\nName\tRole\nEnd\n", 'truncated' => false]);
});

it('replaces every placeholder and adds up what changed', function () {
    Http::fake(['docs.googleapis.com/*' => Http::response([
        'documentId' => 'doc1',
        'replies' => [['replaceAllText' => ['occurrencesChanged' => 2]], ['replaceAllText' => []]],
    ])]);

    $output = (new GoogleDocsReplaceTextNode)->execute(Run::factory()->create(), [
        'access_token' => 'ya29-test', 'document_id' => 'doc1', 'replacements' => ['{{name}}' => 'Ada', '{{date}}' => '1 Jan', '' => 'ignored'], 'match_case' => false,
    ], []);

    expect($output)->toBe(['document_id' => 'doc1', 'occurrences_changed' => 2]);
    Http::assertSent(fn ($request) => count($request['requests']) === 2
        && $request['requests'][0]['replaceAllText'] === ['containsText' => ['text' => '{{name}}', 'matchCase' => false], 'replaceText' => 'Ada']);
});

it('refuses a replace with nothing to replace', function () {
    Http::fake();

    (new GoogleDocsReplaceTextNode)->execute(Run::factory()->create(), ['access_token' => 'ya29-test', 'document_id' => 'doc1', 'replacements' => []], []);
})->throws(InvalidArgumentException::class, 'at least one text');

it('triggers on each revision of a document, with its text', function () {
    Http::fake(['docs.googleapis.com/*' => Http::response([
        'documentId' => 'doc1', 'title' => 'Plan', 'revisionId' => 'rev-7',
        'body' => ['content' => [['paragraph' => ['elements' => [['textRun' => ['content' => "Hello\n"]]]]]]],
    ])]);
    $node = new GoogleDocsReadTextNode;

    $output = $node->execute(Run::factory()->create(), ['access_token' => 'ya29-test', 'document_id' => 'doc1'], []);
    $items = $node->triggerItems($output, []);

    expect($output['revision_id'])->toBe('rev-7')
        ->and($items)->toHaveCount(1)
        ->and($items[0]['text'])->toBe("Hello\n")
        ->and($node->triggerItemId($items[0]))->toBe('doc1:rev-7')
        ->and($node->triggerName())->toBe('Google Docs: Document updated');
});

it('has nothing to trigger on for a document without a revision', function () {
    expect((new GoogleDocsReadTextNode)->triggerItems(['document_id' => 'doc1', 'revision_id' => null], []))->toBe([]);
});
