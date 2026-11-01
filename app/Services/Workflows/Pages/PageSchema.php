<?php

namespace App\Services\Workflows\Pages;

use App\Enums\Workflows\PageBlockType;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\WorkflowInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * The shape of a workflow page, and what's wrong with one.
 *
 * A page is a tree: sections hold blocks and columns, columns hold one
 * column per layout part, and a column holds blocks: see
 * `PageBlockType::fitsIn()`. Containers keep their blocks in `children`.
 *
 * `normalize()` turns whatever the builder sent into the canonical content
 * every reader can rely on: known block types only, each where it may sit,
 * every prop present with a sane value, unsafe URLs dropped. Every block
 * keeps at least one prop, so `props` never round-trips through PHP as `[]`
 * instead of `{}`.
 *
 * `issues()` checks the content against the workflow it runs: that each
 * field fills in an input the workflow has, that nothing the workflow
 * requires is missing, and that each output block shows a value the
 * workflow returns. Errors block publishing; warnings don't.
 *
 * @phpstan-type Block array{id: string, type: string, width: string, props: array<string, mixed>, children?: array<int, mixed>}
 * @phpstan-type Content array{blocks: array<int, Block>, theme: array<string, mixed>}
 * @phpstan-type Issue array{severity: 'error'|'warning', code: string, message: string, block_id: ?string}
 */
class PageSchema
{
    public const int MAX_BLOCKS = 300;

    public const array WIDTHS = ['full', 'two_thirds', 'half', 'third'];

    public const array COLUMN_LAYOUTS = ['1-1', '1-2', '2-1', '1-1-1', '1-1-1-1'];

    public const array FONTS = [
        'sans', 'serif', 'mono',
        'inter', 'dm_sans', 'manrope', 'poppins', 'space_grotesk', 'outfit',
        'playfair', 'lora', 'fraunces', 'jetbrains_mono',
    ];

    private const array ALIGN = ['left', 'center', 'right'];

    private const string ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    public function __construct(private readonly WorkflowInterface $interface) {}

    /**
     * @param  array<string, mixed>  $content
     * @return Content
     */
    public function normalize(array $content): array
    {
        $seen = [];
        $count = 0;

        return [
            'blocks' => $this->blocks($content['blocks'] ?? null, null, $seen, $count),
            'theme' => $this->theme(is_array($content['theme'] ?? null) ? $content['theme'] : []),
        ];
    }

    /**
     * Every block in the tree, parents before their children, in reading
     * order.
     *
     * @param  array<int, Block>  $blocks
     * @return array<int, Block>
     */
    public static function flatten(array $blocks): array
    {
        $flat = [];

        foreach ($blocks as $block) {
            $flat[] = $block;

            if (isset($block['children'])) {
                array_push($flat, ...self::flatten($block['children']));
            }
        }

        return $flat;
    }

    /**
     * A starting layout for a new page: a title, one field per input the
     * workflow asks for, a Run button and one block per value it returns.
     *
     * @return Content
     */
    public function starter(Workflow $workflow, string $title): array
    {
        $blocks = [
            self::block(PageBlockType::Heading, ['text' => $title, 'level' => 1]),
        ];

        if (filled($workflow->description)) {
            $blocks[] = self::block(PageBlockType::Text, ['markdown' => $workflow->description]);
        }

        foreach ($this->interface->describe($workflow)['fields'] as $field) {
            $blocks[] = self::block(PageBlockType::Field, ['field_key' => $field['key']]);
        }

        $blocks[] = self::block(PageBlockType::Submit, ['label' => 'Run']);

        $outputs = $this->interface->outputs($workflow);

        if ($outputs !== []) {
            $blocks[] = self::block(PageBlockType::Divider, []);
            $blocks[] = self::block(PageBlockType::Status, []);

            foreach ($outputs as $output) {
                $blocks[] = self::block(PageBlockType::Output, ['output_name' => $output['name']]);
            }
        } else {
            $blocks[] = self::block(PageBlockType::Status, []);
        }

        return $this->normalize(['blocks' => $blocks, 'theme' => []]);
    }

    /**
     * A template's layout and theme, bound to the inputs and outputs of the
     * workflow a new page runs.
     *
     * Field and output blocks are slots, wherever they sit in the tree. A
     * slot keeps its binding when the workflow has that input or output;
     * otherwise it takes the next one the page doesn't show yet, dropping
     * the wording it had for the old one. Slots left over are removed, and
     * inputs or outputs that didn't get a slot are added after the last
     * slot of their kind, at the same width.
     *
     * The first top-level heading becomes the page's title, and an empty
     * text block right below it takes the workflow's description.
     *
     * @param  array<string, mixed>  $template
     * @return Content
     */
    public function fromTemplate(array $template, Workflow $workflow, string $title): array
    {
        $content = $this->bindToWorkflow($template, $workflow);

        $titled = false;
        $blocks = self::applyTitle($content['blocks'], $title, filled($workflow->description) ? $workflow->description : null, $titled);

        return $this->normalize(['blocks' => $blocks, 'theme' => $content['theme']]);
    }

    /**
     * Content from outside the builder (a template, or a page AI designed)
     * made safe to put in front of the workflow: normalized, every field and
     * output bound to one the workflow has (see `fromTemplate()`), and a Run
     * button added if there isn't one.
     *
     * @param  array<string, mixed>  $content
     * @return Content
     */
    public function bindToWorkflow(array $content, Workflow $workflow): array
    {
        $content = $this->normalize($content);

        $blocks = $this->bindSlots(
            $content['blocks'],
            PageBlockType::Field,
            'field_key',
            array_column($this->interface->describe($workflow)['fields'], 'key'),
            ['label' => null, 'placeholder' => null, 'help' => null, 'variant' => 'auto'],
        );

        $blocks = $this->bindSlots(
            $blocks,
            PageBlockType::Output,
            'output_name',
            array_column($this->interface->outputs($workflow), 'name'),
            ['label' => null, 'empty_text' => null, 'display' => 'auto'],
        );

        if (! collect(self::flatten($blocks))->contains('type', PageBlockType::Submit->value)) {
            $blocks = self::insertAfterLast($blocks, PageBlockType::Field, [self::block(PageBlockType::Submit, ['label' => 'Run'])]);
        }

        return $this->normalize(['blocks' => $blocks, 'theme' => $content['theme']]);
    }

    /**
     * @param  Content  $content
     * @return array<int, Issue>
     */
    public function issues(array $content, ?Workflow $workflow): array
    {
        if ($workflow === null || $workflow->trashed()) {
            return [self::issue('error', 'workflow_missing', 'The workflow this page runs is in the trash. Restore it, or delete this page.')];
        }

        $issues = [];

        if (! $workflow->isPublished()) {
            $issues[] = self::issue('error', 'workflow_unpublished', 'Publish the workflow first. A page can only run its published version.');
        }

        $fields = collect($this->interface->describe($workflow)['fields'])->keyBy('key');
        $outputNames = array_column($this->interface->outputs($workflow), 'name');
        $blocks = self::flatten($content['blocks']);
        $placed = [];

        foreach ($blocks as $block) {
            if ($block['type'] !== PageBlockType::Field->value) {
                continue;
            }

            $key = $block['props']['field_key'];

            if ($key === '') {
                $issues[] = self::issue('error', 'unbound_field', 'Choose which workflow input this field fills in.', $block['id']);
            } elseif (! $fields->has($key)) {
                $issues[] = self::issue('error', 'unknown_field', "The workflow has no input called \"{$key}\" any more. Pick another or remove this field.", $block['id']);
            } elseif (isset($placed[$key])) {
                $issues[] = self::issue('error', 'duplicate_field', "\"{$this->label($fields[$key])}\" is already on the page. Each input can appear once.", $block['id']);
            } else {
                $placed[$key] = true;
            }
        }

        foreach ($fields as $key => $field) {
            if ($field['required'] && $field['default'] === null && ! isset($placed[$key])) {
                $issues[] = self::issue('error', 'missing_required_field', "The workflow needs \"{$this->label($field)}\", but it isn't on the page. Add a field for it.");
            }
        }

        if (! collect($blocks)->contains('type', PageBlockType::Submit->value)) {
            $issues[] = self::issue('error', 'no_submit', 'Add a Run button so people can start the workflow.');
        }

        $showsResult = false;

        foreach ($blocks as $block) {
            if ($block['type'] === PageBlockType::Status->value) {
                $showsResult = true;
            }

            if ($block['type'] !== PageBlockType::Output->value) {
                continue;
            }

            $showsResult = true;
            $name = $block['props']['output_name'];

            if ($name === '') {
                $issues[] = self::issue('error', 'unbound_output', 'Choose which workflow output this block shows.', $block['id']);
            } elseif ($outputNames === []) {
                $issues[] = self::issue('error', 'unknown_output', 'The workflow has no Output steps. Add one to the workflow to show its result here.', $block['id']);
            } elseif (! in_array($name, $outputNames, true)) {
                $issues[] = self::issue('error', 'unknown_output', "The workflow doesn't return \"{$name}\" any more. Pick another output or remove this block.", $block['id']);
            }
        }

        if (! $showsResult) {
            $issues[] = self::issue('warning', 'no_result', 'Nothing on the page shows the result. Add an Output block so people see what the workflow returns.');
        }

        return $issues;
    }

    /**
     * @param  array<int, Issue>  $issues
     */
    public static function hasErrors(array $issues): bool
    {
        return collect($issues)->contains('severity', 'error');
    }

    /**
     * The output names a page's content shows: the only values of a run's
     * result a visitor ever receives.
     *
     * @param  Content  $content
     * @return array<int, string>
     */
    public static function shownOutputs(array $content): array
    {
        return collect(self::flatten($content['blocks']))
            ->where('type', PageBlockType::Output->value)
            ->pluck('props.output_name')
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    public static function newBlockId(): string
    {
        return 'b_'.Str::lower(Str::random(10));
    }

    /**
     * Gives every block in the tree a new id, for blocks copied into a page
     * that may already hold the originals.
     *
     * @param  array<int, Block>  $blocks
     * @return array<int, Block>
     */
    public static function withNewIds(array $blocks): array
    {
        return array_map(function (array $block): array {
            $block['id'] = self::newBlockId();

            if (isset($block['children'])) {
                $block['children'] = self::withNewIds($block['children']);
            }

            return $block;
        }, $blocks);
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private static function block(PageBlockType $type, array $props): array
    {
        return ['id' => self::newBlockId(), 'type' => $type->value, 'width' => 'full', 'props' => $props];
    }

    /**
     * The blocks that may sit in `$parent`, normalized, sharing one id set
     * and one block budget across the whole tree.
     *
     * @param  array<string, true>  $seen
     * @return array<int, Block>
     */
    private function blocks(mixed $raw, ?PageBlockType $parent, array &$seen, int &$count): array
    {
        $blocks = [];

        foreach (array_values(is_array($raw) ? $raw : []) as $block) {
            if ($count >= self::MAX_BLOCKS) {
                break;
            }

            $type = is_array($block) ? PageBlockType::tryFrom((string) ($block['type'] ?? '')) : null;

            if ($type === null || ! $type->fitsIn($parent)) {
                continue;
            }

            $id = (string) ($block['id'] ?? '');

            if (preg_match(self::ID_PATTERN, $id) !== 1 || isset($seen[$id])) {
                $id = self::newBlockId();
            }

            $seen[$id] = true;
            $count++;

            $props = $this->props($type, is_array($block['props'] ?? null) ? $block['props'] : []);
            $normalized = [
                'id' => $id,
                'type' => $type->value,
                // Containers span the page; blocks in a column fill the column.
                'width' => $type->isContainer() || $parent === PageBlockType::Column
                    ? 'full'
                    : self::oneOf($block['width'] ?? null, self::WIDTHS),
                'props' => $props,
            ];

            if ($type->isContainer()) {
                $children = $this->blocks($block['children'] ?? null, $type, $seen, $count);
                $normalized['children'] = $type === PageBlockType::Columns
                    ? $this->fitColumns($children, $props['layout'], $seen, $count)
                    : $children;
            }

            $blocks[] = $normalized;
        }

        return $blocks;
    }

    /**
     * Exactly one column per part of the layout: missing ones are added
     * empty, and the blocks of any extra ones move into the last column
     * rather than being lost.
     *
     * @param  array<int, Block>  $columns
     * @param  array<string, true>  $seen
     * @return array<int, Block>
     */
    private function fitColumns(array $columns, string $layout, array &$seen, int &$count): array
    {
        $wanted = count(explode('-', $layout));

        if (count($columns) > $wanted) {
            $extra = array_merge(...array_map(fn (array $column): array => $column['children'], array_slice($columns, $wanted)));
            $columns = array_slice($columns, 0, $wanted);
            $columns[$wanted - 1]['children'] = [...$columns[$wanted - 1]['children'], ...$extra];
        }

        while (count($columns) < $wanted) {
            $id = self::newBlockId();
            $seen[$id] = true;
            $count++;
            $columns[] = ['id' => $id, 'type' => PageBlockType::Column->value, 'width' => 'full', 'props' => $this->props(PageBlockType::Column, []), 'children' => []];
        }

        return $columns;
    }

    /**
     * Binds every `$type` block's `$prop` to one of `$keys`: see
     * `fromTemplate()`. `$reset` is the wording a slot loses when it moves
     * to a different key.
     *
     * @param  array<int, Block>  $blocks
     * @param  array<int, string>  $keys
     * @param  array<string, mixed>  $reset
     * @return array<int, Block>
     */
    private function bindSlots(array $blocks, PageBlockType $type, string $prop, array $keys, array $reset): array
    {
        $kept = [];

        foreach (self::flatten($blocks) as $block) {
            $key = $block['props'][$prop] ?? '';

            if ($block['type'] === $type->value && in_array($key, $keys, true) && ! isset($kept[$key])) {
                $kept[$key] = $block['id'];
            }
        }

        $unplaced = array_values(array_diff($keys, array_keys($kept)));
        $bound = self::rebind($blocks, $type, $prop, $kept, $unplaced, $reset);

        if ($unplaced !== []) {
            $width = collect(self::flatten($bound))->where('type', $type->value)->last()['width'] ?? 'full';

            $bound = self::insertAfterLast($bound, $type, array_map(
                fn (string $key): array => [...self::block($type, [$prop => $key]), 'width' => $width],
                $unplaced,
            ));
        }

        return self::fillRows($bound, $type);
    }

    /**
     * @param  array<int, Block>  $blocks
     * @param  array<string, string>  $kept
     * @param  array<int, string>  $unplaced  takes the keys as slots claim them
     * @param  array<string, mixed>  $reset
     * @return array<int, Block>
     */
    private static function rebind(array $blocks, PageBlockType $type, string $prop, array $kept, array &$unplaced, array $reset): array
    {
        $bound = [];

        foreach ($blocks as $block) {
            if (isset($block['children'])) {
                $block['children'] = self::rebind($block['children'], $type, $prop, $kept, $unplaced, $reset);
                $bound[] = $block;

                continue;
            }

            if ($block['type'] !== $type->value) {
                $bound[] = $block;

                continue;
            }

            $key = $block['props'][$prop];

            if (($kept[$key] ?? null) === $block['id']) {
                $bound[] = $block;

                continue;
            }

            $next = array_shift($unplaced);

            if ($next === null) {
                continue;
            }

            if ($key !== '') {
                $block['props'] = [...$block['props'], ...$reset];
            }

            $block['props'][$prop] = $next;
            $bound[] = $block;
        }

        return $bound;
    }

    /**
     * Widens the last `$type` block of each run when the run doesn't fill
     * its last row, so a half-width field whose partner slot was dropped
     * doesn't sit alone at half the page. Applied at every level of the tree.
     *
     * @param  array<int, Block>  $blocks
     * @return array<int, Block>
     */
    private static function fillRows(array $blocks, PageBlockType $type): array
    {
        $spans = ['full' => 6, 'two_thirds' => 4, 'half' => 3, 'third' => 2];
        $widths = array_flip($spans);
        $row = 0;

        foreach ($blocks as $i => $block) {
            if (isset($block['children'])) {
                $blocks[$i]['children'] = self::fillRows($block['children'], $type);
            }

            if ($block['type'] !== $type->value) {
                $row = 0;

                continue;
            }

            $span = $spans[$block['width']] ?? 6;
            $row = $row + $span > 6 ? $span : $row + $span;
            $endsRun = ($blocks[$i + 1]['type'] ?? null) !== $type->value;

            if ($endsRun && $row < 6) {
                $blocks[$i]['width'] = $widths[$span + 6 - $row] ?? 'full';
                $row = 0;
            }
        }

        return $blocks;
    }

    /**
     * Puts `$inserted` right after the last `$type` block, in whichever
     * container holds it. With none, fields go before the first top-level
     * block holding the Run button, and anything else goes at the end.
     *
     * @param  array<int, Block>  $blocks
     * @param  array<int, Block>  $inserted
     * @return array<int, Block>
     */
    private static function insertAfterLast(array $blocks, PageBlockType $type, array $inserted): array
    {
        [$placed, $done] = self::insertAfterLastIn($blocks, $type, $inserted);

        if ($done) {
            return $placed;
        }

        $at = count($blocks);

        if ($type === PageBlockType::Field) {
            foreach ($blocks as $i => $block) {
                if (collect(self::flatten([$block]))->contains('type', PageBlockType::Submit->value)) {
                    $at = $i;

                    break;
                }
            }
        }

        array_splice($blocks, $at, 0, $inserted);

        return $blocks;
    }

    /**
     * @param  array<int, Block>  $blocks
     * @param  array<int, Block>  $inserted
     * @return array{0: array<int, Block>, 1: bool}
     */
    private static function insertAfterLastIn(array $blocks, PageBlockType $type, array $inserted): array
    {
        for ($i = count($blocks) - 1; $i >= 0; $i--) {
            if (isset($blocks[$i]['children'])) {
                [$children, $done] = self::insertAfterLastIn($blocks[$i]['children'], $type, $inserted);

                if ($done) {
                    $blocks[$i]['children'] = $children;

                    return [$blocks, true];
                }

                continue;
            }

            if ($blocks[$i]['type'] === $type->value) {
                array_splice($blocks, $i + 1, 0, $inserted);

                return [$blocks, true];
            }
        }

        return [$blocks, false];
    }

    /**
     * Retitles the first top-level heading and fills or drops an empty text
     * block right below it: see `fromTemplate()`.
     *
     * @param  array<int, Block>  $blocks
     * @return array<int, Block>
     */
    private static function applyTitle(array $blocks, string $title, ?string $description, bool &$done): array
    {
        foreach ($blocks as $i => $block) {
            if ($done) {
                break;
            }

            if (isset($block['children'])) {
                $blocks[$i]['children'] = self::applyTitle($block['children'], $title, $description, $done);

                continue;
            }

            if ($block['type'] !== PageBlockType::Heading->value || $block['props']['level'] !== 1) {
                continue;
            }

            $done = true;
            $blocks[$i]['props']['text'] = $title;
            $below = $blocks[$i + 1] ?? null;

            if ($below !== null && $below['type'] === PageBlockType::Text->value && $below['props']['markdown'] === '') {
                if ($description !== null) {
                    $blocks[$i + 1]['props']['markdown'] = $description;
                } else {
                    unset($blocks[$i + 1]);
                }
            }
        }

        return array_values($blocks);
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function props(PageBlockType $type, array $p): array
    {
        return match ($type) {
            PageBlockType::Heading => [
                'text' => self::text($p['text'] ?? null, 300) ?? '',
                'level' => self::oneOf((int) ($p['level'] ?? 2), [2, 1, 3]),
                'align' => self::oneOf($p['align'] ?? null, self::ALIGN),
            ],
            PageBlockType::Text => [
                'markdown' => self::text($p['markdown'] ?? null, 20000) ?? '',
                'align' => self::oneOf($p['align'] ?? null, self::ALIGN),
            ],
            PageBlockType::Image => [
                'url' => self::url($p['url'] ?? null),
                'alt' => self::text($p['alt'] ?? null, 300),
                'fit' => self::oneOf($p['fit'] ?? null, ['cover', 'contain']),
                'height' => self::oneOf($p['height'] ?? null, ['md', 'sm', 'lg', 'auto']),
            ],
            PageBlockType::Divider => [
                'style' => self::oneOf($p['style'] ?? null, ['solid', 'dashed']),
            ],
            PageBlockType::Spacer => [
                'size' => self::oneOf($p['size'] ?? null, ['md', 'sm', 'lg', 'xl']),
            ],
            PageBlockType::Callout => [
                'tone' => self::oneOf($p['tone'] ?? null, ['info', 'success', 'warning', 'danger']),
                'title' => self::text($p['title'] ?? null, 200),
                'markdown' => self::text($p['markdown'] ?? null, 5000) ?? '',
            ],
            PageBlockType::Button => [
                'label' => self::text($p['label'] ?? null, 80) ?? 'Learn more',
                'url' => self::url($p['url'] ?? null),
                'style' => self::oneOf($p['style'] ?? null, ['solid', 'outline', 'ghost']),
                'align' => self::oneOf($p['align'] ?? null, ['left', 'center', 'right', 'stretch']),
                'new_tab' => (bool) ($p['new_tab'] ?? true),
            ],
            PageBlockType::Video => [
                'url' => self::url($p['url'] ?? null),
                'caption' => self::text($p['caption'] ?? null, 300),
            ],
            PageBlockType::Faq => [
                'items' => self::items($p['items'] ?? null, 30, fn (array $item): ?array => self::pair(
                    ['question' => self::text($item['question'] ?? null, 300), 'answer' => self::text($item['answer'] ?? null, 5000)],
                )),
                'open_first' => (bool) ($p['open_first'] ?? true),
            ],
            PageBlockType::Testimonial => [
                'quote' => self::text($p['quote'] ?? null, 1000) ?? '',
                'author' => self::text($p['author'] ?? null, 120),
                'role' => self::text($p['role'] ?? null, 120),
                'avatar_url' => self::url($p['avatar_url'] ?? null),
                'rating' => max(0, min(5, (int) ($p['rating'] ?? 0))),
            ],
            PageBlockType::Stats => [
                'items' => self::items($p['items'] ?? null, 6, fn (array $item): ?array => self::pair(
                    ['value' => self::text($item['value'] ?? null, 40), 'label' => self::text($item['label'] ?? null, 80)],
                )),
                'align' => self::oneOf($p['align'] ?? null, ['center', 'left']),
            ],
            PageBlockType::Logos => [
                'title' => self::text($p['title'] ?? null, 120),
                'items' => self::items($p['items'] ?? null, 12, function (array $item): ?array {
                    $url = self::url($item['url'] ?? null);

                    return $url === null ? null : ['url' => $url, 'alt' => self::text($item['alt'] ?? null, 120)];
                }),
                'grayscale' => (bool) ($p['grayscale'] ?? true),
            ],
            PageBlockType::Tabs => [
                'items' => self::items($p['items'] ?? null, 8, fn (array $item): ?array => self::pair(
                    ['label' => self::text($item['label'] ?? null, 60), 'markdown' => self::text($item['markdown'] ?? null, 10000)],
                )),
            ],
            PageBlockType::Countdown => [
                'target' => self::datetime($p['target'] ?? null),
                'label' => self::text($p['label'] ?? null, 120),
                'ended_text' => self::text($p['ended_text'] ?? null, 200),
                'show_seconds' => (bool) ($p['show_seconds'] ?? true),
            ],
            PageBlockType::Field => [
                'field_key' => self::text($p['field_key'] ?? null, 255) ?? '',
                'label' => self::text($p['label'] ?? null, 200),
                'placeholder' => self::text($p['placeholder'] ?? null, 200),
                'help' => self::text($p['help'] ?? null, 1000),
                'variant' => self::oneOf($p['variant'] ?? null, ['auto', 'input', 'textarea', 'dropdown', 'radio', 'checkbox', 'switch']),
            ],
            PageBlockType::Submit => [
                'label' => self::text($p['label'] ?? null, 80) ?? 'Run',
                'running_label' => self::text($p['running_label'] ?? null, 80) ?? 'Running…',
                'align' => self::oneOf($p['align'] ?? null, ['left', 'center', 'right', 'stretch']),
            ],
            PageBlockType::Output => [
                'output_name' => self::text($p['output_name'] ?? null, 255) ?? '',
                'label' => self::text($p['label'] ?? null, 200),
                'display' => self::oneOf($p['display'] ?? null, ['auto', 'text', 'markdown', 'json', 'table', 'image', 'link']),
                'empty_text' => self::text($p['empty_text'] ?? null, 300),
                'copyable' => (bool) ($p['copyable'] ?? true),
            ],
            PageBlockType::Status => [
                'show_progress' => (bool) ($p['show_progress'] ?? true),
                'idle_text' => self::text($p['idle_text'] ?? null, 300),
            ],
            PageBlockType::Section => [
                'background' => self::oneOf($p['background'] ?? null, ['none', 'subtle', 'accent', 'dark', 'image']),
                'image_url' => self::url($p['image_url'] ?? null),
                'padding' => self::oneOf($p['padding'] ?? null, ['md', 'sm', 'lg']),
                'align' => self::oneOf($p['align'] ?? null, ['left', 'center']),
            ],
            PageBlockType::Columns => [
                'layout' => self::oneOf($p['layout'] ?? null, self::COLUMN_LAYOUTS),
                'gap' => self::oneOf($p['gap'] ?? null, ['md', 'sm', 'lg']),
                'valign' => self::oneOf($p['valign'] ?? null, ['top', 'center']),
            ],
            PageBlockType::Column => [
                'background' => self::oneOf($p['background'] ?? null, ['none', 'subtle']),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $t
     * @return array<string, mixed>
     */
    private function theme(array $t): array
    {
        $accent = (string) ($t['accent'] ?? '');

        return [
            'accent' => preg_match('/^#[0-9a-fA-F]{6}$/', $accent) === 1 ? strtolower($accent) : '#4f46e5',
            'appearance' => self::oneOf($t['appearance'] ?? null, ['light', 'dark', 'auto']),
            'background' => self::oneOf($t['background'] ?? null, ['soft', 'plain', 'dots', 'gradient']),
            'radius' => self::oneOf($t['radius'] ?? null, ['lg', 'none', 'sm', 'md', 'xl']),
            'font' => self::oneOf($t['font'] ?? null, self::FONTS),
            'width' => self::oneOf($t['width'] ?? null, ['medium', 'narrow', 'wide']),
            'logo_url' => self::url($t['logo_url'] ?? null),
            'cover_url' => self::url($t['cover_url'] ?? null),
            'show_branding' => (bool) ($t['show_branding'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function label(array $field): string
    {
        return (string) ($field['label'] ?: $field['key']);
    }

    /**
     * @return Issue
     */
    private static function issue(string $severity, string $code, string $message, ?string $blockId = null): array
    {
        return ['severity' => $severity, 'code' => $code, 'message' => $message, 'block_id' => $blockId];
    }

    /**
     * Up to `$max` list items, each shaped by `$item`; an item it returns
     * null for is dropped.
     *
     * @param  callable(array<string, mixed>): ?array<string, mixed>  $item
     * @return array<int, array<string, mixed>>
     */
    private static function items(mixed $value, int $max, callable $item): array
    {
        $items = [];

        foreach (is_array($value) ? array_values($value) : [] as $raw) {
            if (count($items) >= $max) {
                break;
            }

            $shaped = is_array($raw) ? $item($raw) : null;

            if ($shaped !== null) {
                $items[] = $shaped;
            }
        }

        return $items;
    }

    /**
     * A two-part list item, kept while either part has something in it.
     *
     * @param  array<string, ?string>  $parts
     * @return array<string, string>|null
     */
    private static function pair(array $parts): ?array
    {
        if (array_filter($parts, fn (?string $part): bool => $part !== null) === []) {
            return null;
        }

        return array_map(fn (?string $part): string => $part ?? '', $parts);
    }

    /**
     * The value when it's one of `$allowed`, else the first allowed value.
     *
     * @param  array<int, mixed>  $allowed
     */
    private static function oneOf(mixed $value, array $allowed): mixed
    {
        return in_array($value, $allowed, true) ? $value : $allowed[0];
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, $max, '');
    }

    /**
     * A moment as an ISO 8601 string in UTC, or null when it isn't one.
     */
    private static function datetime(mixed $value): ?string
    {
        $value = self::text($value, 64);

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->utc()->toIso8601ZuluString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Only absolute http(s) URLs: anything else (`javascript:`, `data:`)
     * could run in a visitor's browser.
     */
    private static function url(mixed $value): ?string
    {
        $value = self::text($value, 2048);

        if ($value === null || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) ? $value : null;
    }
}
