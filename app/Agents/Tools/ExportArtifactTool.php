<?php

namespace App\Agents\Tools;

use App\Models\Agent;
use App\Models\Artifact;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lets an agent export a generated file (report, image, code, spreadsheet, HTML, etc.)
 * as a downloadable, versioned artifact. Re-exporting the same filename within the
 * same conversation creates a new version instead of overwriting.
 */
class ExportArtifactTool implements Tool
{
    public function __construct(
        private readonly Agent $agent,
        private readonly Workspace $workspace,
        private readonly ?string $conversationId = null,
        private readonly ?string $agentRunId = null,
        private readonly ?int $userId = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Export a file you generated (report, image, code, spreadsheet, HTML dashboard, etc.) as a '
            .'downloadable artifact. Provide a filename, mime_type, and the file content (UTF-8 text, or '
            .'base64 when is_base64 is true). Re-using the same filename in this conversation creates a new '
            .'version instead of overwriting the previous one.';
    }

    public function handle(Request $request): Stringable|string
    {
        $filename = $request['filename'] ?? '';
        $mimeType = $request['mime_type'] ?? '';
        $content = $request['content'] ?? '';
        $isBase64 = (bool) ($request['is_base64'] ?? false);

        if (! $filename || ! $mimeType || $content === '') {
            return json_encode(['error' => 'filename, mime_type, and content are required.']);
        }

        $decoded = $isBase64 ? base64_decode($content, true) : $content;

        if ($decoded === false) {
            return json_encode(['error' => 'content is not valid base64.']);
        }

        $previous = Artifact::query()
            ->where('workspace_id', $this->workspace->id)
            ->where('conversation_id', $this->conversationId)
            ->where('filename', $filename)
            ->orderByDesc('version')
            ->first();

        $groupId = $previous?->group_id ?? (string) Str::uuid();
        $version = $previous ? $previous->version + 1 : 1;

        $path = "artifacts/{$this->workspace->id}/{$groupId}/v{$version}-{$filename}";
        Storage::disk('local')->put($path, $decoded);

        $artifact = Artifact::create([
            'workspace_id' => $this->workspace->id,
            'agent_id' => $this->agent->id,
            'agent_run_id' => $this->agentRunId,
            'conversation_id' => $this->conversationId,
            'created_by' => $this->userId,
            'group_id' => $groupId,
            'version' => $version,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size' => strlen($decoded),
            'disk' => 'local',
            'path' => $path,
        ]);

        return json_encode([
            'id' => $artifact->id,
            'group_id' => $artifact->group_id,
            'filename' => $artifact->filename,
            'version' => $artifact->version,
            'mime_type' => $artifact->mime_type,
            'size' => $artifact->size,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()->required(),
            'mime_type' => $schema->string()->required(),
            'content' => $schema->string()->required(),
            'is_base64' => $schema->boolean(),
        ];
    }
}
