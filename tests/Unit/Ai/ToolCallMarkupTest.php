<?php

use App\Ai\Support\ToolCallMarkup;

it('strips a complete tool-call block from a finished reply', function () {
    $text = "Done.\n<tool_call>ExportArtifact<arg_key>filename</arg_key><arg_value>a.docx</arg_value></tool_call>\nAll set.";

    expect(ToolCallMarkup::strip($text))->toBe("Done.\nAll set.");
});

it('drops an unclosed block at the end of the reply', function () {
    expect(ToolCallMarkup::strip('Hello <tool_call>Export<arg_key>x</arg_key><arg_value>unfinished'))->toBe('Hello ');
});

it('leaves ordinary text, including angle brackets, untouched', function () {
    expect(ToolCallMarkup::strip('Use <b>bold</b> and a < b.'))->toBe('Use <b>bold</b> and a < b.');
});

it('filters a block split across stream chunks', function () {
    $filter = new ToolCallMarkup;

    $out = $filter->push('Before <tool_');
    $out .= $filter->push('call>Export<arg_key>k</arg_key><arg_val');
    $out .= $filter->push('ue>v</arg_value></tool');
    $out .= $filter->push('_call> After');
    $out .= $filter->finish();

    expect($out)->toBe('Before  After');
});

it('releases a held partial tag once it turns out not to be markup', function () {
    $filter = new ToolCallMarkup;

    $out = $filter->push('a <tool');
    $out .= $filter->push('box> b');
    $out .= $filter->finish();

    expect($out)->toBe('a <toolbox> b');
});

it('keeps the rest of a reply that only mentions the tag', function () {
    $text = 'GLM writes `<tool_call>` into its text. The rest of this reply stays.';

    expect(ToolCallMarkup::strip($text))->toBe($text);
});

it('releases a streamed mention of the tag once the stream ends', function () {
    $filter = new ToolCallMarkup;

    $out = $filter->push('Mention `<tool_call>` here');
    $out .= $filter->push(' and more </tool');
    $out .= $filter->push(' text');
    $out .= $filter->finish();

    expect($out)->toBe('Mention `<tool_call>` here and more </tool text');
});
