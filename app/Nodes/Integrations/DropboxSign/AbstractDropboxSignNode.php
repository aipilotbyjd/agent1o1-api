<?php

namespace App\Nodes\Integrations\DropboxSign;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\CallsApiKeyService;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared by the Dropbox Sign nodes (the e-signature service formerly called
 * HelloSign). The API key is the login: it is sent as the user name of HTTP
 * Basic authentication, with no password.
 *
 * Every send takes a test-mode switch. A request sent in test mode is free and
 * not legally binding, which is what a workflow under construction wants and
 * what a free plan requires.
 */
abstract class AbstractDropboxSignNode implements DeclaresEffect, DeclaresOutputSchema, NodeContract
{
    use CallsApiKeyService;

    protected const string API_URL = 'https://api.hellosign.com/v3';

    public function category(): string
    {
        return 'dropbox_sign';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    protected function serviceName(): string
    {
        return 'Dropbox Sign';
    }

    protected function authenticate(string $key, array $headers, array $query): array
    {
        return [[...$headers, 'Authorization' => 'Basic '.base64_encode($key.':')], $query];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function testModeField(): array
    {
        return [
            'test_mode' => Field::boolean('Test mode', 'Sends a free, non-binding test. Turn it off to send a real request. A free Dropbox Sign plan can only send tests.', false),
        ];
    }

    /**
     * Send a request with files: the API takes those as a multipart form, with
     * nested values written as `signers[0][email_address]`.
     *
     * @param  array<string, mixed>  $fields
     * @param  list<array{content: string, filename: string, mime_type: string}>  $files
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function sendWithFiles(Run $run, array $config, string $path, array $fields, array $files): array
    {
        [$headers] = $this->authenticate($this->resolveAccessToken($run, $config), [], []);

        $request = Http::withHeaders($headers)->acceptJson()->timeout(90)->asMultipart();

        foreach ($files as $index => $file) {
            $request = $request->attach("files[{$index}]", $file['content'], $file['filename'], ['Content-Type' => $file['mime_type']]);
        }

        try {
            $response = $request->post(self::API_URL.$path, $this->flatten($fields));
        } catch (ConnectionException) {
            throw new RuntimeException('Dropbox Sign could not be reached. Try again in a moment.');
        }

        $this->assertSuccessful($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<array{name: string, contents: string}>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $parts = [];

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $name = $prefix === '' ? (string) $key : "{$prefix}[{$key}]";

            if (is_array($value)) {
                array_push($parts, ...$this->flatten($value, $name));
            } else {
                $parts[] = ['name' => $name, 'contents' => is_bool($value) ? ($value ? '1' : '0') : (string) $value];
            }
        }

        return $parts;
    }

    /**
     * People who sign: `[{name, email, order?, role?}]`.
     *
     * @param  array<string, mixed>  $config
     * @return list<array{name: string, email_address: string, order: int, role: string}>
     *
     * @throws InvalidArgumentException
     */
    protected function signers(array $config, bool $needRole = false): array
    {
        $given = $config['signers'] ?? [];

        if (is_string($given)) {
            $given = json_decode($given, true);
        }

        if (! is_array($given) || $given === [] || ! array_is_list($given)) {
            throw new InvalidArgumentException('Add at least one signer: a list of {"name": "...", "email": "..."}.');
        }

        if (count($given) > 20) {
            throw new InvalidArgumentException('A request can have at most 20 signers.');
        }

        $signers = [];

        foreach ($given as $index => $signer) {
            $position = $index + 1;
            $name = trim((string) ($signer['name'] ?? ''));
            $email = strtolower(trim((string) ($signer['email'] ?? $signer['email_address'] ?? '')));
            $role = trim((string) ($signer['role'] ?? ''));

            if ($name === '') {
                throw new InvalidArgumentException("Signer {$position} needs a name.");
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException("Signer {$position}’s email “{$email}” is not an email address.");
            }

            if ($needRole && $role === '') {
                throw new InvalidArgumentException("Signer {$position} needs a role, matching a role in the template.");
            }

            $signers[] = ['name' => $name, 'email_address' => $email, 'order' => $index, 'role' => $role];
        }

        return $signers;
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function requestId(array $config, string $key = 'signature_request_id'): string
    {
        $id = trim((string) ($config[$key] ?? ''));

        if (preg_match('/^[0-9a-f]{20,64}$/i', $id) !== 1) {
            throw new InvalidArgumentException('That is not a signature request ID. Use the ID from a send step or List Signature Requests.');
        }

        return $id;
    }

    /**
     * One signature request, in the shape the nodes return.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    protected function shapeRequest(array $request): array
    {
        $signatures = (array) ($request['signatures'] ?? []);
        $time = fn (mixed $epoch): ?string => is_numeric($epoch) ? Carbon::createFromTimestampUTC((int) $epoch)->toIso8601String() : null;

        $status = match (true) {
            (bool) ($request['is_complete'] ?? false) => 'complete',
            (bool) ($request['is_declined'] ?? false) => 'declined',
            (bool) ($request['has_error'] ?? false) => 'error',
            default => 'awaiting_signature',
        };

        return [
            'signature_request_id' => $request['signature_request_id'] ?? null,
            'status' => $status,
            'title' => $request['title'] ?? null,
            'subject' => $request['subject'] ?? null,
            'created_at' => $time($request['created_at'] ?? null),
            'is_complete' => $status === 'complete',
            'test_mode' => (bool) ($request['test_mode'] ?? false),
            'signers' => array_map(fn (mixed $signature): array => [
                'name' => $signature['signer_name'] ?? null,
                'email' => $signature['signer_email_address'] ?? null,
                'status' => $signature['status_code'] ?? null,
                'signed_at' => $time($signature['signed_at'] ?? null),
            ], $signatures),
            'metadata' => (object) ($request['metadata'] ?? []),
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'signature_request_id' => ['title' => 'Request ID', 'description' => 'Dropbox Sign’s ID for the request.'],
                'status' => ['title' => 'Status', 'description' => 'awaiting_signature, complete, declined or error.'],
                'title' => ['title' => 'Title', 'description' => 'The request’s title.'],
                'signers' => ['title' => 'Signers', 'description' => 'Each: name, email, status and signed_at.'],
                'is_complete' => ['title' => 'Is complete', 'description' => 'True once everyone has signed.'],
            ],
        ];
    }
}
