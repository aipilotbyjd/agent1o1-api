<?php

namespace App\Services\Setup;

use App\Services\Workflows\Pages\PageSchema;

/**
 * Builders for the page blocks used by `database/setup/page-templates.php`, so
 * a template reads as a list of headings, fields and outputs.
 */
final class PageBlocks
{
    public static function photo(string $id): string
    {
        return "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=1600&q=80";
    }

    /**
     * @return array<string, mixed>
     */
    public static function heading(string $text, int $level, string $align = 'left'): array
    {
        return self::block('heading', ['text' => $text, 'level' => $level, 'align' => $align]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function text(string $markdown, string $align = 'left'): array
    {
        return self::block('text', ['markdown' => $markdown, 'align' => $align]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function callout(string $tone, string $title, string $markdown, string $width = 'full'): array
    {
        return self::block('callout', ['tone' => $tone, 'title' => $title, 'markdown' => $markdown], $width);
    }

    /**
     * @return array<string, mixed>
     */
    public static function image(string $url, string $alt, string $height): array
    {
        return self::block('image', ['url' => $url, 'alt' => $alt, 'fit' => 'cover', 'height' => $height]);
    }

    /**
     * An unbound field slot, labelled by the workflow input it gets.
     *
     * @return array<string, mixed>
     */
    public static function field(string $width = 'full'): array
    {
        return self::block('field', [], $width);
    }

    /**
     * @return array<string, mixed>
     */
    public static function submit(string $label, string $runningLabel, string $align): array
    {
        return self::block('submit', ['label' => $label, 'running_label' => $runningLabel, 'align' => $align]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function status(string $idleText): array
    {
        return self::block('status', ['show_progress' => true, 'idle_text' => $idleText]);
    }

    /**
     * An unbound output slot with the look the template gives its result.
     *
     * @return array<string, mixed>
     */
    public static function output(string $label, string $display, string $emptyText, string $width = 'full'): array
    {
        return self::block('output', ['label' => $label, 'display' => $display, 'empty_text' => $emptyText, 'copyable' => true], $width);
    }

    /**
     * A full-width band holding other blocks.
     *
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    public static function section(array $children, string $background = 'none', string $padding = 'md', string $align = 'left', ?string $imageUrl = null): array
    {
        return [...self::block('section', ['background' => $background, 'padding' => $padding, 'align' => $align, 'image_url' => $imageUrl]), 'children' => $children];
    }

    /**
     * Side-by-side columns; each entry of `$columns` is the list of blocks in
     * one column.
     *
     * @param  array<int, array<int, array<string, mixed>>>  $columns
     * @return array<string, mixed>
     */
    public static function columns(string $layout, array $columns, string $gap = 'md', string $valign = 'top', string $columnBackground = 'none'): array
    {
        return [
            ...self::block('columns', ['layout' => $layout, 'gap' => $gap, 'valign' => $valign]),
            'children' => array_map(
                fn (array $children): array => [...self::block('column', ['background' => $columnBackground]), 'children' => $children],
                $columns,
            ),
        ];
    }

    /**
     * Numbered steps in a row, one callout each.
     *
     * @param  array<int, array{0: string, 1: string}>  $steps  [title, text]
     * @return array<string, mixed>
     */
    public static function steps(array $steps, string $tone = 'info'): array
    {
        $layout = match (count($steps)) {
            2 => '1-1',
            4 => '1-1-1-1',
            default => '1-1-1',
        };

        $number = 0;

        return self::columns($layout, array_map(function (array $step) use (&$number, $tone): array {
            $number++;

            return [self::callout($tone, "{$number}. {$step[0]}", $step[1])];
        }, $steps));
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $items  [question, answer]
     * @return array<string, mixed>
     */
    public static function faq(array $items, bool $openFirst = true): array
    {
        return self::block('faq', [
            'items' => array_map(fn (array $item): array => ['question' => $item[0], 'answer' => $item[1]], $items),
            'open_first' => $openFirst,
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $tabs  [label, markdown]
     * @return array<string, mixed>
     */
    public static function tabs(array $tabs): array
    {
        return self::block('tabs', ['items' => array_map(fn (array $tab): array => ['label' => $tab[0], 'markdown' => $tab[1]], $tabs)]);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $items  [value, label]
     * @return array<string, mixed>
     */
    public static function stats(array $items, string $align = 'center'): array
    {
        return self::block('stats', ['items' => array_map(fn (array $item): array => ['value' => $item[0], 'label' => $item[1]], $items), 'align' => $align]);
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public static function block(string $type, array $props, string $width = 'full'): array
    {
        return ['id' => PageSchema::newBlockId(), 'type' => $type, 'width' => $width, 'props' => $props];
    }
}
