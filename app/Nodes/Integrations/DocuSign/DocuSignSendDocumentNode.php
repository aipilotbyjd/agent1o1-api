<?php

namespace App\Nodes\Integrations\DocuSign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Nodes\Support\FilePayload;
use InvalidArgumentException;

class DocuSignSendDocumentNode extends AbstractDocuSignNode
{
    private const array ALLOWED_TYPES = [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/msword' => 'doc',
        'text/plain' => 'txt',
        'text/html' => 'html',
    ];

    public function type(): string
    {
        return 'docusign_send_document';
    }

    public function name(): string
    {
        return 'DocuSign: Send Document for Signature';
    }

    public function description(): string
    {
        return 'Sends a document, such as a PDF a previous step made, for signature, with a signature field at the marker you place.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['signers', 'subject'],
            'properties' => [
                ...$this->accountFields(),
                ...FilePayload::inputFields('Document'),
                'signers' => Field::json('Who signs', 'A list: [{"name": "Ada Lovelace", "email": "ada@example.com"}]. Add "order" to have people sign one after another.', 'array'),
                'subject' => Field::text('Email subject', 'What the signers see.', 'Please sign: Service agreement'),
                'message' => Field::textarea('Email message'),
                'signature_marker' => Field::text('Signature marker', 'Text in the document that marks where a person signs. With one signer use the marker as written (e.g. /sign/). With several, number it for each signer in the document: /sign1/, /sign2/, and so on.', '/sign/'),
                'send' => Field::boolean('Send now', 'Off saves it as a draft in DocuSign to review before sending.', true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $signers = $this->signers($config);
        $subject = trim((string) ($config['subject'] ?? ''));

        if ($subject === '') {
            throw new InvalidArgumentException('Give the email a subject.');
        }

        $file = FilePayload::read($config);
        $extension = self::ALLOWED_TYPES[$file['mime_type']] ?? null;

        if ($extension === null) {
            throw new InvalidArgumentException("DocuSign can send PDF, Word, text and HTML documents, not {$file['mime_type']}.");
        }

        $marker = trim((string) ($config['signature_marker'] ?? '')) ?: '/sign/';

        $envelope = $this->call($run, $config, 'POST', $this->root($run, $config).'/envelopes', array_filter([
            'emailSubject' => mb_substr($subject, 0, 100),
            'emailBlurb' => filled($config['message'] ?? null) ? mb_substr(trim((string) $config['message']), 0, 10000) : null,
            'documents' => [[
                'documentBase64' => base64_encode($file['content']),
                'name' => mb_substr(pathinfo($file['filename'], PATHINFO_FILENAME) ?: 'Document', 0, 100),
                'fileExtension' => $extension,
                'documentId' => '1',
            ]],
            'recipients' => ['signers' => array_map(fn (array $signer, int $index): array => [
                'name' => $signer['name'],
                'email' => $signer['email'],
                'recipientId' => (string) ($index + 1),
                'routingOrder' => (string) $signer['order'],
                'tabs' => ['signHereTabs' => [[
                    // With several signers each has their own numbered marker (/sign1/, /sign2/).
                    'anchorString' => count($signers) > 1 ? $this->numberedMarker($marker, $index + 1) : $marker,
                    'anchorUnits' => 'pixels',
                    'anchorIgnoreIfNotPresent' => true,
                ]]],
            ], $signers, array_keys($signers))],
            'status' => (bool) ($config['send'] ?? true) ? 'sent' : 'created',
        ], fn (mixed $value): bool => $value !== null));

        return ['envelope_id' => $envelope['envelopeId'] ?? null, 'status' => $envelope['status'] ?? null, 'sent_to' => array_column($signers, 'email')];
    }

    /**
     * /sign/ becomes /sign1/ for the first signer, /sign2/ for the second.
     */
    private function numberedMarker(string $marker, int $number): string
    {
        return str_ends_with($marker, '/') ? substr($marker, 0, -1).$number.'/' : $marker.$number;
    }

    public function outputSchema(): array
    {
        return (new DocuSignSendFromTemplateNode)->outputSchema();
    }
}
