<?php

namespace App\Ai\Agents\Concerns;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\ToolChoice;

/**
 * For a one-shot agent whose answer is a structured result described by a
 * submit tool's schema. By default the schema is sent as the provider's
 * structured output (`HasStructuredOutput`) and the agent has no tools. An
 * agent listed in `ai.structured_output.forced_tool` instead gets the
 * submit tool and is forced to call it, which works on any provider that
 * can call tools. `App\Ai\ToolSubmission` reads the result either way.
 *
 * The using class implements `HasStructuredOutput` and `HasTools`.
 */
trait SubmitsStructuredOutput
{
    /**
     * The tool whose name and schema describe the result.
     */
    abstract protected function submissionTool(): Tool;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->submitsNatively() ? $this->submissionTool()->schema($schema) : [];
    }

    /**
     * @return iterable<int, Tool>
     */
    public function tools(): iterable
    {
        return $this->submitsNatively() ? [] : [$this->submissionTool()];
    }

    public function toolChoice(): ?ToolChoice
    {
        return $this->submitsNatively() ? null : ToolChoice::tool($this->submissionTool()->name());
    }

    public function submitsNatively(): bool
    {
        return ! in_array(static::class, (array) config('ai.structured_output.forced_tool', []), true);
    }
}
