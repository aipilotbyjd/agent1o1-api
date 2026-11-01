<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GoogleSlides\GoogleSlidesAddSlideNode;
use App\Nodes\Integrations\GoogleSlides\GoogleSlidesCreatePresentationNode;
use App\Nodes\Integrations\GoogleSlides\GoogleSlidesGetPresentationNode;
use App\Nodes\Integrations\GoogleSlides\GoogleSlidesReplaceTextNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

const SLIDES_BASE = ['access_token' => 'ya29-test'];

it('registers every google slides node under the google_slides category', function () {
    foreach (['create_presentation', 'get_presentation', 'replace_text', 'add_slide'] as $suffix) {
        expect(app(NodeRegistry::class)->resolve("google_slides_{$suffix}")->category())->toBe('google_slides');
    }
});

it('creates a presentation and returns its link', function () {
    Http::fake(['slides.googleapis.com/*' => Http::response(['presentationId' => 'p1', 'title' => 'Review'])]);

    $output = (new GoogleSlidesCreatePresentationNode)->execute(Run::factory()->create(), [...SLIDES_BASE, 'title' => 'Review'], []);

    expect($output)->toBe(['presentation_id' => 'p1', 'title' => 'Review', 'url' => 'https://docs.google.com/presentation/d/p1/edit']);
});

it('reads the text on every slide, including tables', function () {
    Http::fake(['slides.googleapis.com/*' => Http::response([
        'presentationId' => 'p1',
        'title' => 'Review',
        'slides' => [
            ['objectId' => 's1', 'pageElements' => [
                ['shape' => ['text' => ['textElements' => [['textRun' => ['content' => "Q1 results\n"]]]]]],
                ['shape' => ['text' => ['textElements' => [['textRun' => ['content' => "Up 10%\n"]]]]]],
                ['image' => []],
            ]],
            ['objectId' => 's2', 'pageElements' => [
                ['table' => ['tableRows' => [['tableCells' => [
                    ['text' => ['textElements' => [['textRun' => ['content' => 'Region']]]]],
                    ['text' => ['textElements' => [['textRun' => ['content' => 'EU']]]]],
                ]]]]],
            ]],
        ],
    ])]);

    $output = (new GoogleSlidesGetPresentationNode)->execute(Run::factory()->create(), [...SLIDES_BASE, 'presentation_id' => 'p1'], []);

    expect($output)->toBe([
        'presentation_id' => 'p1',
        'title' => 'Review',
        'slide_count' => 2,
        'slides' => [
            ['number' => 1, 'slide_id' => 's1', 'text' => "Q1 results\nUp 10%"],
            ['number' => 2, 'slide_id' => 's2', 'text' => 'RegionEU'],
        ],
    ]);
});

it('replaces placeholders across the presentation', function () {
    Http::fake(['slides.googleapis.com/*' => Http::response(['presentationId' => 'p1', 'replies' => [['replaceAllText' => ['occurrencesChanged' => 3]]]])]);

    $output = (new GoogleSlidesReplaceTextNode)->execute(Run::factory()->create(), [
        ...SLIDES_BASE, 'presentation_id' => 'p1', 'replacements' => ['{{client}}' => 'Acme'],
    ], []);

    expect($output)->toBe(['presentation_id' => 'p1', 'occurrences_changed' => 3]);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/presentations/p1:batchUpdate')
        && $request['requests'][0]['replaceAllText']['containsText'] === ['text' => '{{client}}', 'matchCase' => true]);
});

it('refuses a replace with nothing to replace', function () {
    Http::fake();

    (new GoogleSlidesReplaceTextNode)->execute(Run::factory()->create(), [...SLIDES_BASE, 'presentation_id' => 'p1', 'replacements' => ['' => 'x']], []);
})->throws(InvalidArgumentException::class, 'at least one text');

it('adds a slide and fills its title and body placeholders', function () {
    Http::fake(['slides.googleapis.com/*' => Http::response(['replies' => []])]);

    $output = (new GoogleSlidesAddSlideNode)->execute(Run::factory()->create(), [
        ...SLIDES_BASE, 'presentation_id' => 'p1', 'layout' => 'TITLE_AND_BODY', 'title' => 'Agenda', 'body' => "One\nTwo",
    ], []);

    expect($output['slide_id'])->toStartWith('slide_');
    Http::assertSent(function ($request) {
        $requests = $request['requests'];
        $mappings = $requests[0]['createSlide']['placeholderIdMappings'];

        return $requests[0]['createSlide']['slideLayoutReference'] === ['predefinedLayout' => 'TITLE_AND_BODY']
            && $mappings[0]['layoutPlaceholder'] === ['type' => 'TITLE', 'index' => 0]
            && $mappings[1]['layoutPlaceholder'] === ['type' => 'BODY', 'index' => 0]
            && $requests[1]['insertText'] === ['objectId' => $mappings[0]['objectId'], 'text' => 'Agenda']
            && $requests[2]['insertText'] === ['objectId' => $mappings[1]['objectId'], 'text' => "One\nTwo"];
    });
});

it('adds a blank slide with no placeholders or text', function () {
    Http::fake(['slides.googleapis.com/*' => Http::response(['replies' => []])]);

    (new GoogleSlidesAddSlideNode)->execute(Run::factory()->create(), [...SLIDES_BASE, 'presentation_id' => 'p1', 'layout' => 'BLANK', 'title' => 'ignored'], []);

    Http::assertSent(fn ($request) => count($request['requests']) === 1 && ! isset($request['requests'][0]['createSlide']['placeholderIdMappings']));
});

it('only inserts text that was given', function () {
    Http::fake(['slides.googleapis.com/*' => Http::response(['replies' => []])]);

    (new GoogleSlidesAddSlideNode)->execute(Run::factory()->create(), [...SLIDES_BASE, 'presentation_id' => 'p1', 'layout' => 'TITLE_ONLY', 'title' => 'Hello'], []);

    Http::assertSent(fn ($request) => count($request['requests']) === 2 && $request['requests'][1]['insertText']['text'] === 'Hello');
});

it('falls back to the title and body layout for an unknown layout', function () {
    Http::fake(['slides.googleapis.com/*' => Http::response(['replies' => []])]);

    (new GoogleSlidesAddSlideNode)->execute(Run::factory()->create(), [...SLIDES_BASE, 'presentation_id' => 'p1', 'layout' => 'NOPE'], []);

    Http::assertSent(fn ($request) => $request['requests'][0]['createSlide']['slideLayoutReference']['predefinedLayout'] === 'TITLE_AND_BODY');
});
