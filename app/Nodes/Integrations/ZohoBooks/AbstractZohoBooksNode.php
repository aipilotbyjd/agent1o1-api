<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use App\Services\Connectors\CrmAccessTokens;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Zoho Books
 * node family. Books shares the Zoho login of the other Zoho apps (see
 * `CrmAccessTokens`) and is called at the data center's API address under
 * `/books/v3`, with the organization id as a query setting on every call. Bodies
 * are JSON; a list pages with `page` and `per_page` (at most 200) and says in
 * `page_context.has_more_page` whether there is more. Ids are long numbers.
 */
abstract class AbstractZohoBooksNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public function category(): string
    {
        return 'zoho_books';
    }

    /**
     * Call Zoho Books and return the decoded body.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function books(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->booksResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call Zoho Books and return the response as it is, for a node that treats a 404 as an answer.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function booksResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $data = $this->resolveCredentialData($run, $config);
        $org = trim((string) ($data['organization_id'] ?? ''));

        if (preg_match('/^\d+$/', $org) !== 1) {
            throw new InvalidArgumentException('Add the Zoho Books organization id (a number) to the connection.');
        }

        $login = app(CrmAccessTokens::class)->zohoApp($data, 'books');
        $options = ['query' => array_filter([...$query, 'organization_id' => $org], fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$login['token']])->acceptJson()->timeout(30)->send(strtoupper($method), $login['base'].$path, $options);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful() && (int) ($response->json('code') ?? 0) === 0) {
            return;
        }

        $message = (string) ($response->json('message') ?? "HTTP {$response->status()}");
        $code = $response->json('code');
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Zoho Books: the token was refused or lacks the Books scopes.',
            $response->status() === 403 => ' The Zoho Books user is not allowed to do that.',
            $response->status() === 429 => ' Zoho Books is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Zoho Books error: '.mb_substr($message, 0, 400).($code ? " ({$code})" : '').(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a list page by page until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, string $key, array $query, int $limit): array
    {
        $records = [];
        $page = 1;

        do {
            $want = min(200, $limit - count($records));
            $response = $this->booksResponse($run, $config, 'GET', $path, [...$query, 'page' => $page, 'per_page' => $want]);

            $this->assertOk($response);

            $rows = array_values(array_filter((array) ($response->json($key) ?? []), 'is_array'));
            array_push($records, ...$rows);
            $page++;
        } while ($rows !== [] && (bool) $response->json('page_context.has_more_page') && count($records) < $limit);

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

        return preg_match('/^\d{6,25}$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be a Zoho Books id (a long number).");
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
     * Custom fields as a JSON list of `customfield_id` / `value` pairs; empty when not given.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    protected function customFieldList(array $config): array
    {
        $value = $config['custom_fields'] ?? null;
        $fields = is_string($value) ? (trim($value) === '' ? [] : json_decode($value, true)) : (array) $value;

        if (! is_array($fields) || ($fields !== [] && ! array_is_list($fields))) {
            throw new InvalidArgumentException('The custom fields must be a JSON list, e.g. [{"customfield_id": "460000000012345", "value": "Gold"}].');
        }

        return $fields;
    }

    /**
     * A date as Books wants it (`2026-03-31`); null when the setting is empty.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function date(array $config, string $key): ?string
    {
        if (! filled($config[$key] ?? null)) {
            return null;
        }

        try {
            return Carbon::parse((string) $config[$key])->format('Y-m-d');
        } catch (\Throwable) {
            throw new InvalidArgumentException("The {$key} is not a date.");
        }
    }

    /**
     * The invoice, estimate or bill lines from a JSON list setting.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    protected function lineItems(array $config, string $key = 'line_items'): array
    {
        $value = $config[$key] ?? null;
        $lines = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($lines) || $lines === [] || ! array_is_list($lines)) {
            throw new InvalidArgumentException('The lines must be a JSON list, e.g. [{"name": "Consulting", "rate": 120, "quantity": 2}].');
        }

        foreach ($lines as $line) {
            if (! is_array($line) || (! filled($line['item_id'] ?? null) && ! filled($line['name'] ?? null) && ! filled($line['account_id'] ?? null))) {
                throw new InvalidArgumentException('Each line needs an item_id, a name or an account_id.');
            }
        }

        return array_values($lines);
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @return array<string, mixed>
     */
    protected function shapeInvoice(array $invoice): array
    {
        return ['id' => isset($invoice['invoice_id']) ? (string) $invoice['invoice_id'] : null, 'number' => $invoice['invoice_number'] ?? null, 'customer_id' => $invoice['customer_id'] ?? null, 'customer' => $invoice['customer_name'] ?? null, 'status' => $invoice['status'] ?? null, 'date' => $invoice['date'] ?? null, 'due_date' => $invoice['due_date'] ?? null, 'total' => $invoice['total'] ?? null, 'balance' => $invoice['balance'] ?? null, 'currency' => $invoice['currency_code'] ?? null, 'email' => $invoice['email'] ?? null, 'reference' => $invoice['reference_number'] ?? null, 'created_at' => $invoice['created_time'] ?? null, 'url' => $invoice['invoice_url'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $estimate
     * @return array<string, mixed>
     */
    protected function shapeEstimate(array $estimate): array
    {
        return ['id' => isset($estimate['estimate_id']) ? (string) $estimate['estimate_id'] : null, 'number' => $estimate['estimate_number'] ?? null, 'customer_id' => $estimate['customer_id'] ?? null, 'customer' => $estimate['customer_name'] ?? null, 'status' => $estimate['status'] ?? null, 'date' => $estimate['date'] ?? null, 'expiry_date' => $estimate['expiry_date'] ?? null, 'total' => $estimate['total'] ?? null, 'currency' => $estimate['currency_code'] ?? null, 'created_at' => $estimate['created_time'] ?? null, 'url' => $estimate['estimate_url'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    protected function shapeContact(array $contact): array
    {
        return ['id' => isset($contact['contact_id']) ? (string) $contact['contact_id'] : null, 'name' => $contact['contact_name'] ?? null, 'company' => $contact['company_name'] ?? null, 'type' => $contact['contact_type'] ?? null, 'status' => $contact['status'] ?? null, 'email' => $contact['email'] ?? null, 'phone' => $contact['phone'] ?? null, 'currency' => $contact['currency_code'] ?? null, 'outstanding' => $contact['outstanding_receivable_amount'] ?? null, 'created_at' => $contact['created_time'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    protected function shapePayment(array $payment): array
    {
        return ['id' => isset($payment['payment_id']) ? (string) $payment['payment_id'] : null, 'number' => $payment['payment_number'] ?? null, 'customer_id' => $payment['customer_id'] ?? null, 'customer' => $payment['customer_name'] ?? null, 'amount' => $payment['amount'] ?? null, 'date' => $payment['date'] ?? null, 'mode' => $payment['payment_mode'] ?? null, 'reference' => $payment['reference_number'] ?? null, 'invoice_numbers' => $payment['invoice_numbers'] ?? null, 'created_at' => $payment['created_time'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $bill
     * @return array<string, mixed>
     */
    protected function shapeBill(array $bill): array
    {
        return ['id' => isset($bill['bill_id']) ? (string) $bill['bill_id'] : null, 'number' => $bill['bill_number'] ?? null, 'vendor_id' => $bill['vendor_id'] ?? null, 'vendor' => $bill['vendor_name'] ?? null, 'status' => $bill['status'] ?? null, 'date' => $bill['date'] ?? null, 'due_date' => $bill['due_date'] ?? null, 'total' => $bill['total'] ?? null, 'balance' => $bill['balance'] ?? null, 'currency' => $bill['currency_code'] ?? null, 'created_at' => $bill['created_time'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function shapeItem(array $item): array
    {
        return ['id' => isset($item['item_id']) ? (string) $item['item_id'] : null, 'name' => $item['name'] ?? null, 'sku' => $item['sku'] ?? null, 'rate' => $item['rate'] ?? null, 'unit' => $item['unit'] ?? null, 'description' => $item['description'] ?? null, 'status' => $item['status'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function customerField(string $title = 'Customer'): array
    {
        return Field::dynamic($title, 'zoho_books.customers', null, 'Acme Inc');
    }

    /**
     * @return array<string, mixed>
     */
    protected function vendorField(): array
    {
        return Field::dynamic('Vendor', 'zoho_books.vendors', null, 'Paper Co');
    }
}
