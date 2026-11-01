<?php

namespace App\Ai\Tools;

use App\Enums\Workflows\PageBlockType;
use App\Services\Workflows\Pages\PageSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How `PageDesignerAgent` hands back a page (see `ToolSubmission`). Block
 * props and children are left open here; `PageSchema::bindToWorkflow()`
 * decides what of them is kept.
 */
class SubmitPageTool implements Tool
{
    public const NAME = 'submit_page';

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Submits the designed page: its theme and its blocks, in reading order.';
    }

    public function handle(Request $request): Stringable|string
    {
        return 'Received.';
    }

    public function schema(JsonSchema $schema): array
    {
        $types = array_values(array_filter(
            array_map(fn (PageBlockType $type): string => $type->value, PageBlockType::cases()),
            fn (string $type): bool => $type !== PageBlockType::Column->value,
        ));

        return [
            'theme' => $schema->object([
                'accent' => $schema->string()->description('Accent colour as #rrggbb.')->required(),
                'appearance' => $schema->string()->enum(['light', 'dark'])->required(),
                'background' => $schema->string()->enum(['soft', 'plain', 'dots', 'gradient'])->required(),
                'radius' => $schema->string()->enum(['none', 'sm', 'md', 'lg', 'xl'])->required(),
                'font' => $schema->string()->enum(PageSchema::FONTS)->required(),
                'width' => $schema->string()->enum(['narrow', 'medium', 'wide'])->required(),
            ])->required(),
            'blocks' => $schema->array()->items($schema->object([
                'type' => $schema->string()->enum($types)->required(),
                'width' => $schema->string()->enum(PageSchema::WIDTHS)->description('Share of the row; narrow blocks sit side by side.'),
                'props' => $schema->object(fn () => [])->description('The block\'s settings, as described in the instructions.')->required(),
                'children' => $schema->array()->items($schema->object(fn () => []))->description('Only for section and columns blocks: the blocks inside, in the same shape.'),
            ]))->description('The page from top to bottom.')->required(),
        ];
    }
}
