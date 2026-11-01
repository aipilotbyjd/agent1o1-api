<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use App\Services\Connectors\CrmAccessTokens;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Zoho Desk
 * node family. Desk shares the Zoho login of the other Zoho apps (see
 * `CrmAccessTokens`) and is called at the data center's `desk` address with the
 * organization id in an `orgId` header. Ids are long numbers; a list pages with
 * `from` and `limit` (at most 100) and answers `{"data": [...]}`, or nothing
 * (204) when there is nothing to list.
 */
abstract class AbstractZohoDeskNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public function category(): string
    {
        return 'zoho_desk';
    }

    /**
     * Call Zoho Desk and return the decoded body (an empty array for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function desk(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->deskResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call Zoho Desk and return the response as it is, for a node that treats a 404 as an answer.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function deskResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $data = $this->resolveCredentialData($run, $config);
        $org = trim((string) ($data['org_id'] ?? ''));

        if (preg_match('/^\d+$/', $org) !== 1) {
            throw new InvalidArgumentException('Add the Zoho Desk organization id (a number) to the connection.');
        }

        $login = app(CrmAccessTokens::class)->zohoApp($data, 'desk');
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$login['token'], 'orgId' => $org])->acceptJson()->timeout(30)->send(strtoupper($method), $login['base'].$path, $options);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('message') ?? "HTTP {$response->status()}");
        $code = $response->json('errorCode');
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Zoho Desk: the token was refused or lacks the Desk scopes.',
            $response->status() === 403 => ' The Zoho Desk user is not allowed to do that.',
            $response->status() === 429 => ' Zoho Desk is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Zoho Desk error: '.mb_substr($message, 0, 400).($code ? " ({$code})" : '').(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a list page by page until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, array $query, int $limit): array
    {
        $records = [];

        do {
            $want = min(100, $limit - count($records));
            $response = $this->deskResponse($run, $config, 'GET', $path, [...$query, 'from' => count($records), 'limit' => $want]);

            if ($response->status() !== 204) {
                $this->assertOk($response);
            }

            $rows = array_values(array_filter((array) ($response->json('data') ?? []), 'is_array'));
            array_push($records, ...$rows);
        } while (count($rows) === $want && count($records) < $limit);

        return array_slice($records, 0, $limit);
    }

    /**
     * An id (a long number) from a setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function numericId(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return preg_match('/^\d{6,25}$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be a Zoho Desk id (a long number).");
    }

    /**
     * An optional id: itself, or null when the setting is empty.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function optionalId(array $config, string $key, string $label): ?string
    {
        return filled($config[$key] ?? null) ? $this->numericId($config, $key, $label) : null;
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function requiredString(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        return $value !== '' ? $value : throw new InvalidArgumentException("{$label} is required.");
    }

    /**
     * A checked email address.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function email(array $config, string $key = 'email'): string
    {
        $email = strtolower(trim((string) ($config[$key] ?? '')));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : throw new InvalidArgumentException($email === '' ? 'The email address is required.' : "\"{$email}\" is not an email address.");
    }

    /**
     * Custom fields as a JSON object setting; an empty map when not given.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function customFields(array $config): array
    {
        $value = $config['custom_fields'] ?? null;
        $fields = is_string($value) ? (trim($value) === '' ? [] : json_decode($value, true)) : (array) $value;

        if (! is_array($fields) || ($fields !== [] && array_is_list($fields))) {
            throw new InvalidArgumentException('The custom fields must be a JSON object, e.g. {"cf_plan": "Gold"}.');
        }

        return $fields;
    }

    /**
     * How many records a list node reads: its `limit`, between 1 and `$max`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default, int $max = 1000): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
    }

    /**
     * @param  array<string, mixed>  $ticket
     * @return array<string, mixed>
     */
    protected function shapeTicket(array $ticket): array
    {
        return [
            'id' => isset($ticket['id']) ? (string) $ticket['id'] : null,
            'number' => $ticket['ticketNumber'] ?? null,
            'subject' => $ticket['subject'] ?? null,
            'description' => $ticket['description'] ?? null,
            'status' => $ticket['status'] ?? null,
            'priority' => $ticket['priority'] ?? null,
            'channel' => $ticket['channel'] ?? null,
            'email' => $ticket['email'] ?? data_get($ticket, 'contact.email'),
            'contact_id' => $ticket['contactId'] ?? null,
            'contact' => trim((string) (data_get($ticket, 'contact.firstName').' '.data_get($ticket, 'contact.lastName'))) ?: null,
            'department_id' => $ticket['departmentId'] ?? null,
            'assignee_id' => $ticket['assigneeId'] ?? null,
            'assignee' => trim((string) (data_get($ticket, 'assignee.firstName').' '.data_get($ticket, 'assignee.lastName'))) ?: null,
            'due_at' => $ticket['dueDate'] ?? null,
            'created_at' => $ticket['createdTime'] ?? null,
            'updated_at' => $ticket['modifiedTime'] ?? null,
            'customer_replied_at' => $ticket['customerResponseTime'] ?? null,
            'thread_count' => $ticket['threadCount'] ?? null,
            'url' => $ticket['webUrl'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    protected function shapeContact(array $contact): array
    {
        return ['id' => isset($contact['id']) ? (string) $contact['id'] : null, 'first_name' => $contact['firstName'] ?? null, 'last_name' => $contact['lastName'] ?? null, 'email' => $contact['email'] ?? null, 'phone' => $contact['phone'] ?? $contact['mobile'] ?? null, 'account_id' => $contact['accountId'] ?? null, 'created_at' => $contact['createdTime'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function departmentField(): array
    {
        return Field::dynamic('Department', 'zoho_desk.departments', null, 'Support');
    }

    /**
     * @return array<string, mixed>
     */
    protected function agentField(string $title = 'Assign to'): array
    {
        return Field::advanced(Field::dynamic($title, 'zoho_desk.agents', null, 'Ada Lovelace'));
    }
}
