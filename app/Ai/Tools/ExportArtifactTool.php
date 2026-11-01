<?php

namespace App\Ai\Tools;

use App\Actions\Artifacts\StoreArtifactAction;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use App\Services\Artifacts\DocumentRenderer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Lets an agent export a generated file (report, image, code, spreadsheet,
 * HTML, etc.) as a downloadable, versioned `Artifact`. Re-exporting the same
 * filename within the same session creates a new version instead of
 * overwriting: see `Artifact::versions()`/`group_id`. Auto-attached to
 * every agent by `ToolRegistry`, same as `RememberTool`. Storage itself
 * (versioning, path layout, filename safety) lives in `StoreArtifactAction`,
 * shared with the Internal API's upload endpoint.
 *
 * Binary documents can't be written reliably as base64 by a model, so a
 * `format` of pdf/xlsx/docx has the model write text (Markdown, HTML, CSV or
 * JSON rows) and `DocumentRenderer` builds the real file from it.
 */
class ExportArtifactTool implements Tool
{
    public function __construct(
        private readonly Agent $agent,
        private readonly AgentSession $session,
        private readonly Run $run,
        private readonly StoreArtifactAction $storeArtifact,
        private readonly DocumentRenderer $renderer = new DocumentRenderer,
    ) {}

    public function description(): Stringable|string
    {
        return 'Export a file you generated (report, image, code, spreadsheet, HTML dashboard, etc.) as a '
            .'downloadable artifact. Provide a filename, mime_type, and the file content (UTF-8 text, or '
            .'base64 when is_base64 is true). '
            .'For a PDF, Excel or Word file, set format to "pdf", "xlsx" or "docx" and write the content as text instead: '
            .'pdf takes Markdown or a full HTML document; xlsx takes CSV, or JSON {"sheets":[{"name":"...","rows":[["Header",...],[...]]}]}; '
            .'docx takes Markdown. Never try to write PDF, Excel or Word bytes yourself. '
            .'Re-using the same filename in this conversation creates a new '
            .'version instead of overwriting the previous one. '
            .'IMPORTANT: you must call this tool every single time you export or re-export a file, including '
            .'when a user asks you to regenerate, update, or redo a file you already exported earlier in this '
            .'conversation. Never claim a file was exported or a new version was created unless you actually '
            .'called this tool for that exact request: a fresh call is required each time, even if the content '
            .'or filename is unchanged.';
    }

    public function handle(Request $request): Stringable|string
    {
        $filename = (string) ($request['filename'] ?? '');
        $mimeType = (string) ($request['mime_type'] ?? '');
        $content = (string) ($request['content'] ?? '');
        $isBase64 = (bool) ($request['is_base64'] ?? false);
        $format = strtolower((string) ($request['format'] ?? ''));

        if ($format !== '') {
            if (! array_key_exists($format, DocumentRenderer::FORMATS)) {
                return json_encode(['error' => 'format must be one of: '.implode(', ', array_keys(DocumentRenderer::FORMATS)).'.']);
            }

            $mimeType = DocumentRenderer::FORMATS[$format];
            $filename = (pathinfo($filename, PATHINFO_FILENAME) ?: 'document').'.'.$format;
            $isBase64 = false;
        }

        if ($filename === '' || $mimeType === '' || $content === '') {
            return json_encode(['error' => 'filename, mime_type, and content are required.']);
        }

        $decoded = $isBase64 ? base64_decode($content, true) : $content;

        if ($decoded === false) {
            return json_encode(['error' => 'content is not valid base64.']);
        }

        if ($format !== '') {
            try {
                $decoded = $this->renderer->render($format, $decoded);
            } catch (Throwable $e) {
                return json_encode(['error' => "Could not build the {$format} file: {$e->getMessage()}"]);
            }
        }

        $artifact = $this->storeArtifact->execute(
            workspace: $this->agent->workspace,
            filename: $filename,
            mimeType: $mimeType,
            contents: $decoded,
            agent: $this->agent,
            session: $this->session,
            run: $this->run,
            createdBy: $this->session->user_id,
        );

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
            'mime_type' => $schema->string()->description('Ignored when format is set.')->required(),
            'content' => $schema->string()->required(),
            'is_base64' => $schema->boolean(),
            'format' => $schema->string()->enum(array_keys(DocumentRenderer::FORMATS))
                ->description('Build a real PDF, Excel or Word file from text content.'),
        ];
    }
}
