<?php

namespace App\Ai\Assistant\Tools;

use App\Actions\Artifacts\StoreArtifactAction;
use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\AssistantSession;
use App\Services\Artifacts\DocumentRenderer;
use App\Services\Assistant\Computer\Sandboxes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;
use Symfony\Component\Mime\MimeTypes;

/**
 * Hands the owner a file: one written as text (or built as a PDF, Excel or
 * Word file from text), or one made on the cloud computer. It lands in the
 * workspace's Artifacts, private to the owner until they share it, and the
 * same name again in this conversation is a new version.
 */
class ExportFileTool extends AssistantTool
{
    public const string NAME = 'export_file';

    public function __construct(
        private readonly AssistantSession $session,
        private readonly StoreArtifactAction $store,
        private readonly DocumentRenderer $renderer,
        private readonly ?Sandboxes $sandboxes,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function effect(): AssistantToolEffect
    {
        return AssistantToolEffect::Internal;
    }

    public function description(): Stringable|string
    {
        return 'Gives the person a downloadable file (it appears in the chat and in Artifacts). Either write the content as text: '
            .'set format to pdf (Markdown or HTML), xlsx (CSV or JSON {"sheets":[{"name":"…","rows":[[…]]}]}) or docx (Markdown) to build a real document: '
            .($this->sandboxes !== null ? 'or pass sandbox_path to hand over a file you made with run_code. ' : '')
            .'Saving the same filename again in this conversation creates a new version. Only say a file is ready after this tool succeeds.';
    }

    protected function execute(Request $request): string
    {
        $filename = basename(trim((string) ($request['filename'] ?? '')));
        $format = strtolower((string) ($request['format'] ?? ''));
        $sandboxPath = trim((string) ($request['sandbox_path'] ?? ''));

        if ($filename === '') {
            return 'Give a filename.';
        }

        if ($sandboxPath !== '') {
            if ($this->sandboxes === null) {
                return 'There is no cloud computer in this conversation; write the content instead.';
            }

            $bytes = $this->sandboxes->readFile($this->session, $sandboxPath);
            $mimeType = $this->mimeFor($filename);
        } elseif ($format !== '') {
            if (! array_key_exists($format, DocumentRenderer::FORMATS)) {
                return 'format must be one of: '.implode(', ', array_keys(DocumentRenderer::FORMATS)).'.';
            }

            $bytes = $this->renderer->render($format, (string) $request['content']);
            $mimeType = DocumentRenderer::FORMATS[$format];
            $filename = (pathinfo($filename, PATHINFO_FILENAME) ?: 'document').'.'.$format;
        } else {
            $bytes = (string) ($request['content'] ?? '');
            $mimeType = $this->mimeFor($filename);
        }

        if ($bytes === '') {
            return 'The file would be empty. Give content or a sandbox_path.';
        }

        $assistant = $this->session->assistant;

        $artifact = $this->store->execute(
            workspace: $assistant->workspace,
            filename: $filename,
            mimeType: $mimeType,
            contents: $bytes,
            createdBy: $assistant->user_id,
            searchable: false,
            assistantSession: $this->session,
        );

        return json_encode([
            'artifact_id' => $artifact->id,
            'filename' => $artifact->filename,
            'version' => $artifact->version,
            'mime_type' => $artifact->mime_type,
            'size' => $artifact->size,
        ], JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function mimeFor(string $filename): string
    {
        return MimeTypes::getDefault()->getMimeTypes(strtolower(pathinfo($filename, PATHINFO_EXTENSION)))[0] ?? 'application/octet-stream';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()->required(),
            'content' => $schema->string()->description('The file as text. Leave out with sandbox_path.'),
            'format' => $schema->string()->enum(array_keys(DocumentRenderer::FORMATS))->description('Build a real PDF, Excel or Word file from text content.'),
            'sandbox_path' => $schema->string()->description('A file on your cloud computer, e.g. /home/user/chart.png.'),
        ];
    }
}
