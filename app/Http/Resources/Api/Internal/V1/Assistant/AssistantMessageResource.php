<?php

namespace App\Http\Resources\Api\Internal\V1\Assistant;

use App\Ai\Assistant\Tools\ExportFileTool;
use App\Ai\Assistant\Tools\RunCodeTool;
use App\Models\Assistant\AssistantMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssistantMessage
 */
class AssistantMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assistant_session_id' => $this->assistant_session_id,
            'role' => $this->role->value,
            'content' => $this->content,
            'tool_calls' => collect($this->tool_calls ?? [])->map(fn (array $call): array => [
                'id' => $call['id'] ?? null,
                'name' => $call['name'] ?? null,
            ])->values(),
            'tool_call_id' => $this->tool_call_id,
            'attachments' => $this->attachments ?? [],
            // Files the assistant handed over in this reply (`export_file`).
            'files' => collect($this->tool_results ?? [])
                ->filter(fn (array $result): bool => ($result['name'] ?? null) === ExportFileTool::NAME)
                ->map(fn (array $result): ?array => json_decode((string) ($result['result'] ?? ''), true))
                ->filter(fn ($file): bool => is_array($file) && isset($file['artifact_id']))
                ->values(),
            // Code the assistant ran on its computer (`run_code`), with what it printed.
            'code_runs' => collect($this->tool_results ?? [])
                ->filter(fn (array $result): bool => ($result['name'] ?? null) === RunCodeTool::NAME)
                ->map(fn (array $result): array => [
                    'id' => $result['id'] ?? null,
                    'language' => $result['arguments']['language'] ?? 'python',
                    'code' => (string) ($result['arguments']['code'] ?? ''),
                    'output' => is_array($decoded = json_decode((string) ($result['result'] ?? ''), true)) ? $decoded : ['stdout' => (string) ($result['result'] ?? '')],
                ])
                ->values(),
            'compacted' => $this->compacted_into_id !== null,
            'feedback' => $this->whenLoaded('feedback', fn () => $this->feedback === null ? null : [
                'rating' => $this->feedback->rating->value,
                'comment' => $this->feedback->comment,
                'status' => $this->feedback->status->value,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
