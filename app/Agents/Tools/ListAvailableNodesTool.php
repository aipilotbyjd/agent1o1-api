<?php

namespace App\Agents\Tools;

use App\Models\Node;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListAvailableNodesTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'List all available workflow node types. Returns name, type key, description, category, and node_kind. Optionally filter by category. Use this to discover what nodes exist before building.';
    }

    public function handle(Request $request): Stringable|string
    {
        $category = $request['category'] ?? null;
        $cacheKey = $category ? "nodes_catalog:category:{$category}" : 'nodes_catalog:all';

        $nodes = Cache::remember($cacheKey, 300, function () use ($category) {
            $query = Node::query()->select(['type', 'name', 'description', 'category', 'node_kind']);

            if ($category) {
                $query->where('category', $category);
            }

            return $query->orderBy('category')->orderBy('name')->get()->toArray();
        });

        return json_encode($nodes, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->description('Optional: filter by category (e.g. "triggers", "actions", "transforms")'),
        ];
    }
}
