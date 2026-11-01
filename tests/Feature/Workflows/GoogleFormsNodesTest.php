<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GoogleForms\GoogleFormsAddQuestionNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsCreateFormNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsDeleteItemNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsGetFormNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsGetResponseNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsListResponsesNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsUpdateFormInfoNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

const FORMS_BASE = ['access_token' => 'ya29-test'];

const FEEDBACK_FORM = [
    'formId' => 'form1',
    'info' => ['title' => 'Feedback', 'description' => 'Tell us'],
    'responderUri' => 'https://docs.google.com/forms/d/e/abc/viewform',
    'items' => [
        ['title' => 'Your name', 'questionItem' => ['question' => ['questionId' => 'q1', 'textQuestion' => []]]],
        ['title' => 'Rating', 'questionItem' => ['question' => ['questionId' => 'q2']]],
        ['title' => 'Satisfaction', 'questionGroupItem' => ['questions' => [['questionId' => 'q3', 'rowQuestion' => ['title' => 'Support']]]]],
        ['title' => 'A page break', 'pageBreakItem' => []],
    ],
];

const ADA_RESPONSE = [
    'responseId' => 'r1',
    'createTime' => '2026-01-01T10:00:00Z',
    'lastSubmittedTime' => '2026-01-01T10:00:05Z',
    'respondentEmail' => 'ada@example.com',
    'answers' => [
        'q1' => ['textAnswers' => ['answers' => [['value' => 'Ada']]]],
        'q2' => ['textAnswers' => ['answers' => [['value' => '5'], ['value' => '4']]]],
        'q9' => ['fileUploadAnswers' => ['answers' => [['fileId' => 'file1']]]],
    ],
];

it('registers every google forms node under the google_forms category', function () {
    foreach (['get_form', 'list_responses', 'get_response', 'create_form', 'update_form_info', 'add_question', 'delete_item'] as $suffix) {
        expect(app(NodeRegistry::class)->resolve("google_forms_{$suffix}")->category())->toBe('google_forms');
    }
});

it('reads a form with its questions', function () {
    Http::fake(['forms.googleapis.com/*' => Http::response(FEEDBACK_FORM)]);

    $output = (new GoogleFormsGetFormNode)->execute(Run::factory()->create(), [...FORMS_BASE, 'form_id' => 'form1'], []);

    expect($output)->toBe([
        'form_id' => 'form1',
        'title' => 'Feedback',
        'description' => 'Tell us',
        'responder_url' => 'https://docs.google.com/forms/d/e/abc/viewform',
        'questions' => [
            ['question_id' => 'q1', 'question' => 'Your name'],
            ['question_id' => 'q2', 'question' => 'Rating'],
            ['question_id' => 'q3', 'question' => 'Satisfaction Support'],
        ],
    ]);
});

it('lists responses with each answer next to its question', function () {
    Http::fake([
        'forms.googleapis.com/v1/forms/form1/responses*' => Http::response(['responses' => [ADA_RESPONSE]]),
        'forms.googleapis.com/v1/forms/form1' => Http::response(FEEDBACK_FORM),
    ]);

    $output = (new GoogleFormsListResponsesNode)->execute(Run::factory()->create(), [...FORMS_BASE, 'form_id' => 'form1', 'submitted_after' => '2026-01-01T00:00:00Z'], []);

    expect($output['responses'][0])->toBe([
        'response_id' => 'r1',
        'created_at' => '2026-01-01T10:00:00Z',
        'submitted_at' => '2026-01-01T10:00:05Z',
        'respondent_email' => 'ada@example.com',
        'answers' => [
            ['question_id' => 'q1', 'question' => 'Your name', 'value' => 'Ada'],
            ['question_id' => 'q2', 'question' => 'Rating', 'value' => '5, 4'],
            ['question_id' => 'q9', 'question' => 'q9', 'value' => 'file1'],
        ],
        'answers_by_question' => ['Your name' => 'Ada', 'Rating' => '5, 4', 'q9' => 'file1'],
    ]);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/responses') && str_contains(urldecode(str_replace('+', ' ', $request->url())), 'filter=timestamp > 2026-01-01T00:00:00Z'));
});

it('keeps two answers whose questions share a title', function () {
    $form = ['items' => [
        ['title' => 'Email', 'questionItem' => ['question' => ['questionId' => 'a']]],
        ['title' => 'Email', 'questionItem' => ['question' => ['questionId' => 'b']]],
    ]];

    Http::fake([
        'forms.googleapis.com/v1/forms/form1/responses*' => Http::response(['responses' => [[
            'responseId' => 'r2', 'answers' => ['a' => ['textAnswers' => ['answers' => [['value' => 'one@x.com']]]], 'b' => ['textAnswers' => ['answers' => [['value' => 'two@x.com']]]]],
        ]]]),
        'forms.googleapis.com/*' => Http::response($form),
    ]);

    $output = (new GoogleFormsListResponsesNode)->execute(Run::factory()->create(), [...FORMS_BASE, 'form_id' => 'form1'], []);

    expect($output['responses'][0]['answers_by_question'])->toBe(['Email' => 'one@x.com', 'Email (b)' => 'two@x.com']);
});

it('works as a new response trigger keyed by response id', function () {
    $node = new GoogleFormsListResponsesNode;

    expect($node->triggerItems(['responses' => [['response_id' => 'r1']]], []))->toHaveCount(1)
        ->and($node->triggerItemId(['response_id' => 'r1']))->toBe('r1')
        ->and($node->triggerName())->toBe('Google Forms: New response');
});

it('reads one response', function () {
    Http::fake([
        'forms.googleapis.com/v1/forms/form1/responses/r1' => Http::response(ADA_RESPONSE),
        'forms.googleapis.com/v1/forms/form1' => Http::response(FEEDBACK_FORM),
    ]);

    $output = (new GoogleFormsGetResponseNode)->execute(Run::factory()->create(), [...FORMS_BASE, 'form_id' => 'form1', 'response_id' => 'r1'], []);

    expect($output['answers_by_question']['Your name'])->toBe('Ada');
});

it('creates a form, then adds its description and questions in order', function () {
    Http::fake([
        'forms.googleapis.com/v1/forms' => Http::response(['formId' => 'new1', 'info' => ['title' => 'Survey'], 'responderUri' => 'https://docs.google.com/forms/d/e/new1/viewform']),
        'forms.googleapis.com/*' => Http::response([]),
    ]);

    $output = (new GoogleFormsCreateFormNode)->execute(Run::factory()->create(), [
        ...FORMS_BASE, 'title' => 'Survey', 'description' => 'Two quick questions', 'questions' => ['Name?', ' ', 'Why?'], 'paragraph_questions' => true,
    ], []);

    expect($output)->toBe([
        'form_id' => 'new1',
        'title' => 'Survey',
        'responder_url' => 'https://docs.google.com/forms/d/e/new1/viewform',
        'edit_url' => 'https://docs.google.com/forms/d/new1/edit',
        'question_count' => 2,
    ]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/forms') && $request['info'] === ['title' => 'Survey', 'documentTitle' => 'Survey']);
    Http::assertSent(function ($request) {
        if (! str_ends_with($request->url(), '/forms/new1:batchUpdate')) {
            return false;
        }

        $requests = $request['requests'];

        return count($requests) === 3
            && $requests[0]['updateFormInfo']['updateMask'] === 'description'
            && $requests[1]['createItem']['item']['title'] === 'Name?'
            && $requests[1]['createItem']['location'] === ['index' => 0]
            && $requests[2]['createItem']['location'] === ['index' => 1]
            && $requests[2]['createItem']['item']['questionItem']['question']['textQuestion'] === ['paragraph' => true];
    });
});

it('creates a bare form with a single request', function () {
    Http::fake(['forms.googleapis.com/*' => Http::response(['formId' => 'new2', 'info' => ['title' => 'Empty']])]);

    (new GoogleFormsCreateFormNode)->execute(Run::factory()->create(), [...FORMS_BASE, 'title' => 'Empty'], []);

    Http::assertSentCount(1);
});

it('does not read the form when a poll finds no responses', function () {
    Http::fake([
        'forms.googleapis.com/v1/forms/form1/responses*' => Http::response([]),
        'forms.googleapis.com/*' => Http::response(FEEDBACK_FORM),
    ]);

    $output = (new GoogleFormsListResponsesNode)->execute(Run::factory()->create(), [...FORMS_BASE, 'form_id' => 'form1'], []);

    expect($output)->toBe(['responses' => []]);
    Http::assertSentCount(1);
});

it('reads only the last day of responses for the trigger', function () {
    Carbon::setTestNow('2026-03-01 10:00:00');
    $node = new GoogleFormsListResponsesNode;

    $config = $node->triggerReadConfig(['form_id' => 'form1']);

    expect($config['submitted_after'])->toBe('2026-02-28T10:00:00Z')
        ->and($config['max_results'])->toBe(500);
    Carbon::setTestNow();
});

it('changes only the title or description that is filled in', function () {
    Http::fake(['forms.googleapis.com/*' => Http::response([])]);
    $node = new GoogleFormsUpdateFormInfoNode;

    $output = $node->execute(Run::factory()->create(), [...FORMS_BASE, 'form_id' => 'form1', 'title' => ' New title ', 'description' => ''], []);

    expect($output)->toBe(['updated' => true, 'form_id' => 'form1', 'title' => 'New title']);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/forms/form1:batchUpdate') && $request['requests'] === [['updateFormInfo' => ['info' => ['title' => 'New title'], 'updateMask' => 'title']]]);
    expect(fn () => $node->execute(Run::factory()->create(), [...FORMS_BASE, 'form_id' => 'form1'], []))->toThrow(InvalidArgumentException::class, 'Change at least');
});

it('adds a choice question at the end, and returns the ids Google made', function () {
    Http::fake([
        'forms.googleapis.com/v1/forms/form1:batchUpdate' => Http::response(['replies' => [['createItem' => ['itemId' => 'item9', 'questionId' => ['q9']]]]]),
        'forms.googleapis.com/v1/forms/form1' => Http::response(FEEDBACK_FORM),
    ]);

    $output = (new GoogleFormsAddQuestionNode)->execute(Run::factory()->create(), [...FORMS_BASE, 'form_id' => 'form1', 'title' => 'Pick one', 'kind' => 'checkboxes', 'options' => ['A', ' ', 'B', 'A'], 'required' => true, 'help_text' => 'Any'], []);

    expect($output)->toBe(['added' => true, 'form_id' => 'form1', 'item_id' => 'item9', 'question_id' => 'q9', 'position' => 4]);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), ':batchUpdate') && $request['requests'][0]['createItem'] === [
        'item' => ['title' => 'Pick one', 'description' => 'Any', 'questionItem' => ['question' => ['required' => true, 'choiceQuestion' => ['type' => 'CHECKBOX', 'options' => [['value' => 'A'], ['value' => 'B']]]]]],
        'location' => ['index' => 4],
    ]);
});

it('adds scale and text questions at a given position, and checks them', function () {
    Http::fake(['forms.googleapis.com/*' => Http::response(['replies' => [['createItem' => ['itemId' => 'i', 'questionId' => ['q']]]]])]);
    $node = new GoogleFormsAddQuestionNode;
    $base = [...FORMS_BASE, 'form_id' => 'form1', 'title' => 'How was it?'];

    $node->execute(Run::factory()->create(), [...$base, 'kind' => 'scale', 'scale_high' => 10, 'high_label' => 'Great', 'position' => 0], []);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), ':batchUpdate') && $request['requests'][0]['createItem']['item']['questionItem']['question']['scaleQuestion'] === ['low' => 1, 'high' => 10, 'highLabel' => 'Great'] && $request['requests'][0]['createItem']['location'] === ['index' => 0]);
    expect(fn () => $node->execute(Run::factory()->create(), [...$base, 'kind' => 'dropdown', 'position' => 0], []))->toThrow(InvalidArgumentException::class, 'at least one choice')
        ->and(fn () => $node->execute(Run::factory()->create(), [...$base, 'kind' => 'scale', 'scale_low' => 1, 'scale_high' => 1, 'position' => 0], []))->toThrow(InvalidArgumentException::class, 'A scale runs')
        ->and(fn () => $node->execute(Run::factory()->create(), [...$base, 'kind' => 'bogus', 'position' => 0], []))->toThrow(InvalidArgumentException::class, 'not a question type');
});

it('deletes the one question that matches, by title, item id or question id', function () {
    $form = ['items' => [
        ['itemId' => 'a1', 'title' => 'Name', 'questionItem' => ['question' => ['questionId' => 'qa']]],
        ['itemId' => 'b2', 'title' => 'Email', 'questionItem' => ['question' => ['questionId' => 'qb']]],
        ['itemId' => 'c3', 'title' => 'Email', 'questionItem' => ['question' => ['questionId' => 'qc']]],
    ]];
    Http::fake(['forms.googleapis.com/v1/forms/form1:batchUpdate' => Http::response([]), 'forms.googleapis.com/v1/forms/form1' => Http::response($form)]);
    $node = new GoogleFormsDeleteItemNode;

    expect($node->execute(Run::factory()->create(), [...FORMS_BASE, 'form_id' => 'form1', 'item' => 'qc'], []))->toBe(['deleted' => true, 'form_id' => 'form1', 'title' => 'Email', 'position' => 2]);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), ':batchUpdate') && $request['requests'] === [['deleteItem' => ['location' => ['index' => 2]]]]);
    expect(fn () => $node->execute(Run::factory()->create(), [...FORMS_BASE, 'form_id' => 'form1', 'item' => 'Email'], []))->toThrow(InvalidArgumentException::class, 'More than one question')
        ->and(fn () => $node->execute(Run::factory()->create(), [...FORMS_BASE, 'form_id' => 'form1', 'item' => 'Nope'], []))->toThrow(InvalidArgumentException::class, 'No question matches');
});
