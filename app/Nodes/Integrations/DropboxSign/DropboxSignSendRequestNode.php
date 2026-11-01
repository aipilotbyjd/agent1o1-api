<?php

namespace App\Nodes\Integrations\DropboxSign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Nodes\Support\FilePayload;
use InvalidArgumentException;

class DropboxSignSendRequestNode extends AbstractDropboxSignNode
{
    private const array ALLOWED_TYPES = [
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'image/jpeg', 'image/png',
    ];

    public function type(): string
    {
        return 'dropbox_sign_send_request';
    }

    public function name(): string
    {
        return 'Dropbox Sign: Send Document for Signature';
    }

    public function description(): string
    {
        return 'Sends a document, such as a PDF a previous step made, to the people who must sign it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['signers', 'title'],
            'properties' => [
                ...$this->credentialFields(),
                ...FilePayload::inputFields('Document'),
                'signers' => Field::json('Who signs', 'A list: [{"name": "Ada Lovelace", "email": "ada@example.com"}]. They sign in this order.', 'array'),
                'title' => Field::text('Title', 'Names the request in Dropbox Sign.', 'Service agreement'),
                'subject' => Field::text('Email subject', 'Leave empty to use the title.'),
                'message' => Field::textarea('Email message'),
                'cc' => Field::advanced(Field::list('Copy to', 'Email addresses that get the signed copy but do not sign.', 'legal@example.com')),
                ...$this->testModeField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $signers = $this->signers($config);
        $title = trim((string) ($config['title'] ?? ''));

        if ($title === '') {
            throw new InvalidArgumentException('Give the request a title.');
        }

        $file = FilePayload::read($config);

        if (! in_array($file['mime_type'], self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException("Dropbox Sign can send PDF, Word, Excel, PowerPoint, text and image files, not {$file['mime_type']}.");
        }

        $copies = array_values(array_filter(array_map(fn (mixed $email): string => strtolower(trim((string) $email)), is_array($config['cc'] ?? null) ? $config['cc'] : []), fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));

        $body = $this->sendWithFiles($run, $config, '/signature_request/send', [
            'title' => mb_substr($title, 0, 255),
            'subject' => mb_substr(trim((string) ($config['subject'] ?? '')) ?: $title, 0, 255),
            'message' => filled($config['message'] ?? null) ? mb_substr(trim((string) $config['message']), 0, 5000) : null,
            'signers' => array_map(fn (array $signer): array => ['name' => $signer['name'], 'email_address' => $signer['email_address'], 'order' => $signer['order']], $signers),
            'cc_email_addresses' => $copies ?: null,
            'signing_options' => ['draw' => true, 'type' => true, 'upload' => true, 'phone' => false, 'default_type' => 'draw'],
            'test_mode' => (bool) ($config['test_mode'] ?? false),
        ], [$file]);

        $request = $this->shapeRequest((array) ($body['signature_request'] ?? []));

        return [...$request, 'sent_to' => array_column($signers, 'email_address')];
    }

    public function outputSchema(): array
    {
        $schema = parent::outputSchema();
        $schema['properties']['sent_to'] = ['title' => 'Sent to', 'description' => 'The signers’ email addresses.'];

        return $schema;
    }
}
