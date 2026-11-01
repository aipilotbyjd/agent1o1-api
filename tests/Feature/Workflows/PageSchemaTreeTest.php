<?php

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPage;
use App\Services\Workflows\Pages\PagePresenter;
use App\Services\Workflows\Pages\PageSchema;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->workflow = Workflow::factory()->forWorkspace($this->workspace)->create(['description' => null]);
    $this->workflow->replaceGraph([
        'nodes' => [
            ['key' => 'topic', 'type' => 'workflow_input', 'config' => ['name' => 'topic', 'required' => true], 'position' => ['x' => 0, 'y' => 0]],
            ['key' => 'tone', 'type' => 'workflow_input', 'config' => ['name' => 'tone'], 'position' => ['x' => 0, 'y' => 100]],
            ['key' => 'note_out', 'type' => 'workflow_output', 'config' => ['name' => 'note', 'value' => '{{ input.topic }}'], 'position' => ['x' => 300, 'y' => 0]],
        ],
        'edges' => [['from' => 'topic', 'to' => 'note_out']],
    ]);
    $this->workflow->publishVersion(publisher: $this->owner);
    $this->schema = app(PageSchema::class);
});

/**
 * @param  array<string, mixed>  $props
 * @param  array<int, array<string, mixed>>|null  $children
 * @return array<string, mixed>
 */
function treeBlock(string $type, array $props = [], ?array $children = null, string $width = 'full'): array
{
    return ['id' => PageSchema::newBlockId(), 'type' => $type, 'width' => $width, 'props' => $props, ...($children === null ? [] : ['children' => $children])];
}

it('keeps each block only where it may sit', function () {
    $content = $this->schema->normalize(['blocks' => [
        treeBlock('column', [], []),
        treeBlock('section', [], [
            treeBlock('section', [], []),
            treeBlock('heading', ['text' => 'Inside'], null, 'half'),
            treeBlock('columns', ['layout' => '1-1'], [
                treeBlock('column', [], [
                    treeBlock('columns', [], []),
                    treeBlock('text', ['markdown' => 'Left'], null, 'half'),
                ]),
                treeBlock('heading', ['text' => 'Not a column']),
            ]),
        ]),
    ]]);

    $section = $content['blocks'][0];

    expect($content['blocks'])->toHaveCount(1);
    expect($section['type'])->toBe('section');
    expect(array_column($section['children'], 'type'))->toBe(['heading', 'columns']);
    expect($section['children'][0]['width'])->toBe('half');

    $columns = $section['children'][1];

    expect(array_column($columns['children'], 'type'))->toBe(['column', 'column']);
    expect(array_column($columns['children'][0]['children'], 'type'))->toBe(['text']);
    expect($columns['children'][0]['children'][0]['width'])->toBe('full');
    expect($columns['children'][1]['children'])->toBe([]);
});

it('fits columns to their layout without losing blocks', function () {
    $content = $this->schema->normalize(['blocks' => [
        treeBlock('columns', ['layout' => '1-1'], [
            treeBlock('column', [], [treeBlock('text', ['markdown' => 'A'])]),
            treeBlock('column', [], [treeBlock('text', ['markdown' => 'B'])]),
            treeBlock('column', [], [treeBlock('text', ['markdown' => 'C'])]),
        ]),
        treeBlock('columns', ['layout' => '1-1-1'], [
            treeBlock('column', [], []),
        ]),
    ]]);

    [$two, $three] = $content['blocks'];

    expect($two['children'])->toHaveCount(2);
    expect(array_column(array_column($two['children'][1]['children'], 'props'), 'markdown'))->toBe(['B', 'C']);
    expect($three['children'])->toHaveCount(3);
});

it('sanitizes the new content blocks', function () {
    $blocks = $this->schema->normalize(['blocks' => [
        treeBlock('button', ['label' => 'Go', 'url' => 'javascript:alert(1)', 'style' => 'weird']),
        treeBlock('faq', ['items' => [['question' => 'Q?', 'answer' => 'A.'], ['question' => '', 'answer' => ''], 'junk']]),
        treeBlock('stats', ['items' => array_fill(0, 9, ['value' => '10x', 'label' => 'Faster'])]),
        treeBlock('logos', ['items' => [['url' => 'https://example.com/a.png'], ['url' => 'ftp://nope']]]),
        treeBlock('testimonial', ['quote' => 'Great', 'rating' => 9]),
        treeBlock('countdown', ['target' => '2030-01-02T10:00:00+02:00']),
        treeBlock('countdown', ['target' => 'not a date']),
    ]])['blocks'];

    expect($blocks[0]['props'])->toMatchArray(['label' => 'Go', 'url' => null, 'style' => 'solid']);
    expect($blocks[1]['props']['items'])->toBe([['question' => 'Q?', 'answer' => 'A.']]);
    expect($blocks[2]['props']['items'])->toHaveCount(6);
    expect($blocks[3]['props']['items'])->toBe([['url' => 'https://example.com/a.png', 'alt' => null]]);
    expect($blocks[4]['props']['rating'])->toBe(5);
    expect($blocks[5]['props']['target'])->toBe('2030-01-02T08:00:00Z');
    expect($blocks[6]['props']['target'])->toBeNull();
});

it('checks fields and the Run button wherever they sit', function () {
    $content = $this->schema->normalize(['blocks' => [
        treeBlock('section', [], [
            treeBlock('columns', ['layout' => '1-1'], [
                treeBlock('column', [], [treeBlock('field', ['field_key' => 'topic'])]),
                treeBlock('column', [], [treeBlock('submit'), treeBlock('output', ['output_name' => 'note'])]),
            ]),
        ]),
    ]]);

    expect($this->schema->issues($content, $this->workflow))->toBe([]);
    expect(PageSchema::shownOutputs($content))->toBe(['note']);
});

it('hands visitors nested fields with their definitions and drops unknown ones', function () {
    $content = $this->schema->normalize(['blocks' => [
        treeBlock('section', [], [
            treeBlock('field', ['field_key' => 'topic']),
            treeBlock('field', ['field_key' => 'gone']),
        ]),
    ]]);
    $page = WorkflowPage::factory()->forWorkspace($this->workspace)->create(['workflow_id' => $this->workflow->id, 'content' => $content]);

    $section = app(PagePresenter::class)->present($page, $content)['blocks'][0];

    expect($section['children'])->toHaveCount(1);
    expect($section['children'][0]['field']['key'])->toBe('topic');
});

it('binds template slots inside columns and adds missing ones next to them', function () {
    $content = $this->schema->fromTemplate(['blocks' => [
        treeBlock('heading', ['text' => 'Title', 'level' => 1]),
        treeBlock('columns', ['layout' => '1-1'], [
            treeBlock('column', [], [treeBlock('field'), treeBlock('submit')]),
            treeBlock('column', [], [treeBlock('output'), treeBlock('output')]),
        ]),
    ]], $this->workflow, 'My page');

    [$heading, $columns] = $content['blocks'];
    [$left, $right] = $columns['children'];

    expect($heading['props']['text'])->toBe('My page');
    expect(array_column(array_column($left['children'], 'props'), 'field_key'))->toBe(['topic', 'tone']);
    expect(array_column($left['children'], 'type'))->toBe(['field', 'field', 'submit']);
    expect(array_column(array_column($right['children'], 'props'), 'output_name'))->toBe(['note']);
    expect($this->schema->issues($content, $this->workflow))->toBe([]);
});
