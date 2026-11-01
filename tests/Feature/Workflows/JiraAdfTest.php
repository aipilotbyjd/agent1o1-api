<?php

use App\Nodes\Integrations\Jira\JiraAdf;

it('turns markdown into the document Jira stores', function () {
    $doc = JiraAdf::fromMarkdown("# Title\n\nSome **bold**, _italic_, `code` and [a link](https://example.com/a?b=1).\nSecond line\n\n- one\n- two\n\n1. first\n2. second\n\n> quoted\n> more\n\n```php\necho 1;\n```");

    expect($doc['type'])->toBe('doc')
        ->and($doc['version'])->toBe(1)
        ->and(array_column($doc['content'], 'type'))->toBe(['heading', 'paragraph', 'bulletList', 'orderedList', 'blockquote', 'codeBlock']);

    [$heading, $paragraph, $bullets, $numbers, $quote, $code] = $doc['content'];

    expect($heading['attrs'])->toBe(['level' => 1])
        ->and(collect($paragraph['content'])->firstWhere('text', 'bold')['marks'])->toBe([['type' => 'strong']])
        ->and(collect($paragraph['content'])->firstWhere('text', 'italic')['marks'])->toBe([['type' => 'em']])
        ->and(collect($paragraph['content'])->firstWhere('text', 'code')['marks'])->toBe([['type' => 'code']])
        ->and(collect($paragraph['content'])->firstWhere('text', 'a link')['marks'])->toBe([['type' => 'link', 'attrs' => ['href' => 'https://example.com/a?b=1']]])
        ->and(collect($paragraph['content'])->contains(fn (array $node): bool => $node['type'] === 'hardBreak'))->toBeTrue()
        ->and($bullets['content'])->toHaveCount(2)
        ->and($numbers['content'])->toHaveCount(2)
        ->and($quote['content'][0]['type'])->toBe('paragraph')
        ->and($code['attrs'])->toBe(['language' => 'php'])
        ->and($code['content'][0]['text'])->toBe('echo 1;');
});

it('makes plain text a paragraph, and nothing an empty one Jira will still take', function () {
    expect(JiraAdf::fromMarkdown('just text')['content'])->toBe([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'just text']]]])
        ->and(JiraAdf::fromMarkdown('')['content'])->toBe([['type' => 'paragraph', 'content' => []]]);
});

it('never makes a link out of an address that is not web or mail', function () {
    $doc = JiraAdf::fromMarkdown('[click](javascript:alert(1)) and [fine](mailto:a@b.com)');

    $marks = collect($doc['content'][0]['content'])->pluck('marks')->filter()->flatten(1)->pluck('attrs.href')->all();

    expect($marks)->toBe(['mailto:a@b.com']);
});

it('reads a document back as text', function () {
    $doc = [
        'type' => 'doc', 'version' => 1,
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Steps']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ask '], ['type' => 'mention', 'attrs' => ['id' => 'x', 'text' => '@Ada']], ['type' => 'hardBreak'], ['type' => 'text', 'text' => 'now']]],
            ['type' => 'bulletList', 'content' => [
                ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'one']]]]],
                ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'two']]]]],
            ]],
            ['type' => 'orderedList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'first']]]]]]],
            ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => 'echo 1;']]],
            ['type' => 'mediaSingle', 'content' => [['type' => 'media', 'attrs' => ['id' => 'f']]]],
            ['type' => 'paragraph', 'content' => [['type' => 'inlineCard', 'attrs' => ['url' => 'https://example.com']]]],
        ],
    ];

    expect(JiraAdf::toText($doc))->toBe("## Steps\n\nAsk @Ada\nnow\n\n- one\n- two\n\n1. first\n\n```\necho 1;\n```\n\n[attachment]\n\nhttps://example.com");
});

it('reads anything that is not a document without failing', function () {
    expect(JiraAdf::toText('already text'))->toBe('already text')
        ->and(JiraAdf::toText(null))->toBe('')
        ->and(JiraAdf::toText(42))->toBe('')
        ->and(JiraAdf::toText([]))->toBe('');
});
