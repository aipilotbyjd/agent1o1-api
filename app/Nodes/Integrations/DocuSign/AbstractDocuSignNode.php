<?php

namespace App\Nodes\Integrations\DocuSign;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\CallsApiKeyService;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared by the DocuSign nodes. DocuSign logs a person in with OAuth, then
 * every account they can reach lives on its own server: `userinfo` says which
 * ones, and each call goes to that account's `base_uri`. The `account` setting
 * picks one; left empty it uses the login's default account.
 *
 * The login server is the live one unless the install points at DocuSign's
 * developer sandbox (`services.docusign.auth_host`).
 */
abstract class AbstractDocuSignNode implements DeclaresEffect, DeclaresOutputSchema, NodeContract
{
    use CallsApiKeyService;

    public function category(): string
    {
        return 'docusign';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    protected function serviceName(): string
    {
        return 'DocuSign';
    }

    protected function authenticate(string $key, array $headers, array $query): array
    {
        return [[...$headers, 'Authorization' => 'Bearer '.$key], $query];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function accountFields(): array
    {
        return [
            ...$this->credentialFields(),
            'account' => Field::advanced(Field::text('DocuSign account', 'Only if your login reaches several accounts: the account ID or name. Leave empty for the default account.')),
        ];
    }

    /**
     * The account's eSignature API address, e.g. https://na3.docusign.net/restapi/v2.1/accounts/{id}.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws RuntimeException
     */
    protected function root(Run $run, array $config): string
    {
        $token = $this->resolveAccessToken($run, $config);

        $accounts = Cache::remember('docusign:accounts:'.sha1($token), 300, function () use ($token): array {
            try {
                $response = Http::withToken($token)->acceptJson()->timeout(20)->get('https://'.config('services.docusign.auth_host', 'account.docusign.com').'/oauth/userinfo');
            } catch (ConnectionException) {
                throw new RuntimeException('DocuSign could not be reached. Try again in a moment.');
            }

            $this->assertSuccessful($response);

            return array_values(array_filter(array_map(fn (mixed $account): array => [
                'id' => (string) ($account['account_id'] ?? ''),
                'name' => (string) ($account['account_name'] ?? ''),
                'default' => (bool) ($account['is_default'] ?? false),
                'base_uri' => rtrim((string) ($account['base_uri'] ?? ''), '/'),
            ], (array) $response->json('accounts')), fn (array $account): bool => $account['id'] !== '' && str_starts_with($account['base_uri'], 'https://')));
        });

        if ($accounts === []) {
            throw new RuntimeException('This DocuSign login has no account that can send documents. Connect a login that does.');
        }

        $wanted = strtolower(trim((string) ($config['account'] ?? '')));
        $account = $wanted === ''
            ? (collect($accounts)->firstWhere('default', true) ?? $accounts[0])
            : collect($accounts)->first(fn (array $candidate): bool => in_array($wanted, [strtolower($candidate['id']), strtolower($candidate['name'])], true));

        if ($account === null) {
            throw new InvalidArgumentException("This login has no DocuSign account called “{$config['account']}”. It can use: ".implode(', ', array_map(fn (array $a): string => $a['name'] ?: $a['id'], $accounts)).'.');
        }

        return $account['base_uri'].'/restapi/v2.1/accounts/'.rawurlencode($account['id']);
    }

    /**
     * People who sign, as DocuSign's `signers`: `[{name, email, role?, order?}]`.
     *
     * @param  array<string, mixed>  $config
     * @return list<array{name: string, email: string, role: string, order: int}>
     *
     * @throws InvalidArgumentException
     */
    protected function signers(array $config): array
    {
        $given = $config['signers'] ?? [];

        if (is_string($given)) {
            $given = json_decode($given, true);
        }

        if (! is_array($given) || $given === [] || ! array_is_list($given)) {
            throw new InvalidArgumentException('Add at least one signer: a list of {"name": "...", "email": "..."}.');
        }

        if (count($given) > 20) {
            throw new InvalidArgumentException('An envelope can have at most 20 signers.');
        }

        $signers = [];

        foreach ($given as $index => $signer) {
            $position = $index + 1;
            $name = trim((string) ($signer['name'] ?? ''));
            $email = strtolower(trim((string) ($signer['email'] ?? '')));

            if ($name === '') {
                throw new InvalidArgumentException("Signer {$position} needs a name.");
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException("Signer {$position}’s email “{$email}” is not an email address.");
            }

            $signers[] = ['name' => $name, 'email' => $email, 'role' => trim((string) ($signer['role'] ?? '')), 'order' => max(1, (int) ($signer['order'] ?? $position))];
        }

        return $signers;
    }

    /**
     * One envelope, in the shape the nodes return.
     *
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    protected function shapeEnvelope(array $envelope): array
    {
        $signers = array_map(fn (mixed $signer): array => [
            'name' => $signer['name'] ?? null,
            'email' => $signer['email'] ?? null,
            'status' => $signer['status'] ?? null,
            'signed_at' => $signer['signedDateTime'] ?? null,
            'role' => $signer['roleName'] ?? null,
        ], (array) ($envelope['recipients']['signers'] ?? []));

        return [
            'envelope_id' => $envelope['envelopeId'] ?? null,
            'status' => $envelope['status'] ?? null,
            'subject' => $envelope['emailSubject'] ?? null,
            'created_at' => $envelope['createdDateTime'] ?? null,
            'sent_at' => $envelope['sentDateTime'] ?? null,
            'completed_at' => $envelope['completedDateTime'] ?? null,
            'declined_at' => $envelope['declinedDateTime'] ?? null,
            'voided_reason' => $envelope['voidedReason'] ?? null,
            'signers' => $signers,
        ];
    }

    /**
     * An envelope ID checked before it goes into an address.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function envelopeId(array $config): string
    {
        $id = trim((string) ($config['envelope_id'] ?? ''));

        if (preg_match('/^[0-9a-fA-F-]{36}$/', $id) !== 1) {
            throw new InvalidArgumentException('That is not an envelope ID. Use the ID from a send step or List Envelopes.');
        }

        return $id;
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'envelope_id' => ['title' => 'Envelope ID', 'description' => 'DocuSign’s ID for the envelope.'],
                'status' => ['title' => 'Status', 'description' => 'created, sent, delivered, completed, declined or voided.'],
                'subject' => ['title' => 'Subject', 'description' => 'The email subject the signers see.'],
                'signers' => ['title' => 'Signers', 'description' => 'Each: name, email, status, signed_at and role.'],
            ],
        ];
    }
}
