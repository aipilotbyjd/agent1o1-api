<?php

namespace App\Nodes\Integrations\DropboxSign;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Nodes\Support\FilePayload;

class DropboxSignDownloadFilesNode extends AbstractDropboxSignNode
{
    public function type(): string
    {
        return 'dropbox_sign_download_files';
    }

    public function name(): string
    {
        return 'Dropbox Sign: Download Signed Document';
    }

    public function description(): string
    {
        return 'Downloads the signed document as one PDF, to file in Drive, attach to an email or keep as a record.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['signature_request_id'],
            'properties' => [
                ...$this->credentialFields(),
                'signature_request_id' => Field::text('Request ID', 'From a send step or List Signature Requests.', 'fa5c8a0b0f492d768749333ad6fcc214c111e967'),
                'filename' => Field::advanced(Field::text('File name', null, 'signed-agreement.pdf')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->requestId($config);
        $document = $this->download($run, $config, self::API_URL."/signature_request/files/{$id}", ['file_type' => 'pdf']);

        $name = preg_replace('/[^\w. -]/u', '_', trim((string) ($config['filename'] ?? ''))) ?: "signed-{$id}.pdf";
        $file = FilePayload::make($document['content'], str_ends_with(strtolower($name), '.pdf') ? $name : $name.'.pdf', 'application/pdf');

        return ['file' => $file, 'size' => $file['size'], 'signature_request_id' => $id];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'file' => ['title' => 'Signed PDF', 'description' => 'The document as a file: filename, mime_type, size and content_base64. Attach it with {{ nodes.download.file }}.'],
                'size' => ['title' => 'Size', 'description' => 'The file size in bytes.'],
                'signature_request_id' => ['title' => 'Request ID', 'description' => 'The request it came from.'],
            ],
        ];
    }
}
