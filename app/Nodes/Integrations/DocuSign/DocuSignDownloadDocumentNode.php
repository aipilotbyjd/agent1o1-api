<?php

namespace App\Nodes\Integrations\DocuSign;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Nodes\Support\FilePayload;

class DocuSignDownloadDocumentNode extends AbstractDocuSignNode
{
    public function type(): string
    {
        return 'docusign_download_document';
    }

    public function name(): string
    {
        return 'DocuSign: Download Signed Document';
    }

    public function description(): string
    {
        return 'Downloads the signed document as one PDF, to file in Drive, attach to an email or keep as a record.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['envelope_id'],
            'properties' => [
                ...$this->accountFields(),
                'envelope_id' => Field::text('Envelope ID', 'From a send step or List Envelopes.', 'a1b2c3d4-0000-0000-0000-000000000000'),
                'include_certificate' => Field::advanced(Field::boolean('Include the certificate of completion', 'Adds DocuSign’s record of who signed and when, as a final page.', false)),
                'filename' => Field::advanced(Field::text('File name', null, 'signed-agreement.pdf')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->envelopeId($config);
        $document = $this->download($run, $config, $this->root($run, $config)."/envelopes/{$id}/documents/combined", ['certificate' => (bool) ($config['include_certificate'] ?? false) ? 'true' : null]);

        $name = preg_replace('/[^\w. -]/u', '_', trim((string) ($config['filename'] ?? ''))) ?: "signed-{$id}.pdf";
        $file = FilePayload::make($document['content'], str_ends_with(strtolower($name), '.pdf') ? $name : $name.'.pdf', 'application/pdf');

        return ['file' => $file, 'size' => $file['size'], 'envelope_id' => $id];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'file' => ['title' => 'Signed PDF', 'description' => 'The document as a file: filename, mime_type, size and content_base64. Attach it with {{ nodes.download.file }}.'],
                'size' => ['title' => 'Size', 'description' => 'The file size in bytes.'],
                'envelope_id' => ['title' => 'Envelope ID', 'description' => 'The envelope it came from.'],
            ],
        ];
    }
}
