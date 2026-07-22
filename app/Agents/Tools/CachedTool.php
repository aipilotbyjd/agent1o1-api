<?php

namespace App\Agents\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;
use Stringable;

/**
 * Tool result caching / dedup (roadmap item 8). Wraps a read-only tool so that
 * repeated calls with identical arguments within a single run return the first
 * result instead of hitting the underlying API again — cutting latency and cost
 * on chatty runs.
 *
 * The cache is per-instance (one instance is built per run), so it never leaks
 * results across runs or users. The decorator delegates name/description/schema
 * verbatim so the model sees an ordinary tool.
 */
class CachedTool implements Tool
{
    /** @var array<string, string> */
    private array $cache = [];

    public function __construct(
        private readonly Tool $inner,
    ) {}

    public function name(): string
    {
        return ToolNameResolver::resolve($this->inner);
    }

    public function description(): Stringable|string
    {
        return $this->inner->description();
    }

    public function handle(Request $request): Stringable|string
    {
        $key = $this->cacheKey($request);

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $result = (string) $this->inner->handle($request);

        return $this->cache[$key] = $result;
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->inner->schema($schema);
    }

    /**
     * Read-only tools are safe to cache. This helper lets the runner decide
     * which underlying tools to wrap.
     */
    public static function shouldCache(Tool $tool): bool
    {
        return $tool instanceof WorkflowNodeTool
            || $tool instanceof WebBrowseTool;
    }

    private function cacheKey(Request $request): string
    {
        $args = $request->all();
        ksort($args);

        return md5(json_encode($args) ?: serialize($args));
    }
}
