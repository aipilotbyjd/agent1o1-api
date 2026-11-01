<?php

namespace App\Nodes\Integrations\Typeform;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Typeform
 * node family. The connection is a personal access token (and, for an account on
 * the EU data center, `region: eu`, which moves every call to api.eu.typeform.com).
 *
 * A Typeform answer names its question only by id and ref, so the nodes that
 * return responses also read the form once and hand back each answer with its
 * question's title: `answers` as a list, `answers_map` keyed by title and
 * `answers_by_ref` keyed by the ref, which stays the same when a question is renamed.
 */
abstract class AbstractTypeformNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    /**
     * Responses Typeform returns in one page.
     */
    protected const int RESPONSE_PAGE = 1000;

    public function category(): string
    {
        return 'typeform';
    }

    /**
     * The API host of the connected account's data center.
     *
     * @param  array<string, mixed>  $config
     */
    protected function host(Run $run, array $config): string
    {
        $region = '';

        try {
            $region = strtolower(trim((string) ($this->resolveCredentialData($run, $config)['region'] ?? '')));
        } catch (RuntimeException) {
            // A raw token has no connection to read a region from: the US host is right for it.
        }

        return $region === 'eu' ? 'https://api.eu.typeform.com' : 'https://api.typeform.com';
    }

    /**
     * Call Typeform and return the decoded body (null for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|list<mixed>|null  $body
     * @param  array<string, mixed>  $query  null values are dropped
     *
     * @throws RuntimeException
     */
    protected function typeform(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = []): mixed
    {
        $request = Http::withToken($this->resolveAccessToken($run, $config))->acceptJson()->timeout(30);
        $query = array_filter($query, fn (mixed $value): bool => $value !== null);
        $options = ['query' => $query];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        return $this->decode($request->send(strtoupper($method), $this->host($run, $config).$path, $options));
    }

    /**
     * @throws RuntimeException
     */
    protected function decode(Response $response): mixed
    {
        if ($response->successful()) {
            return $response->status() === 204 || $response->body() === '' ? null : $response->json();
        }

        $detail = collect((array) $response->json('details'))->map(fn (mixed $item): string => is_array($item) ? trim(($item['field'] ?? '').' '.($item['description'] ?? '')) : '')->filter()->implode('; ');
        $message = (string) ($response->json('description') ?? $response->json('message') ?? "HTTP {$response->status()}");
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Typeform: the token was refused.',
            $response->status() === 403 => ' The token needs more scopes (forms, responses, webhooks, workspaces).',
            $response->status() === 429 => ' Typeform is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException("Typeform error: {$message}".($detail !== '' ? " ({$detail})" : '').".{$hint}");
    }

    /**
     * A form id from the id itself or a link to the form (`https://x.typeform.com/to/AbC123`).
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function formId(array $config, string $key = 'form_id'): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException('The form is required.');
        }

        if (preg_match('~/(?:to|forms)/([A-Za-z0-9]+)~', $value, $match) === 1) {
            $value = $match[1];
        }

        return preg_match('/^[A-Za-z0-9]{4,20}$/', $value) === 1
            ? $value
            : throw new InvalidArgumentException("\"{$value}\" is not a Typeform form id. It is the code in the form's link, e.g. AbC123 in typeform.com/to/AbC123.");
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
     * How many records a list node reads: its `limit`, between 1 and `$max`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default, int $max = 1000): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
    }

    /**
     * A JSON setting that may arrive as text from a template.
     *
     * @return array<int|string, mixed>|null
     *
     * @throws InvalidArgumentException
     */
    protected function decoded(mixed $value, string $label): ?array
    {
        if (is_string($value)) {
            if (trim($value) === '') {
                return null;
            }

            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : throw new InvalidArgumentException("{$label} must be valid JSON.");
        }

        return is_array($value) ? $value : null;
    }

    /**
     * Read every page of a form's responses (newest first) until `$limit` are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function readResponses(Run $run, array $config, string $form, array $query, int $limit): array
    {
        $responses = [];
        $before = null;

        do {
            $want = min(self::RESPONSE_PAGE, $limit - count($responses));
            $page = (array) $this->typeform($run, $config, 'GET', "/forms/{$form}/responses", query: [...$query, 'page_size' => $want, 'before' => $before]);
            $items = array_values(array_filter((array) ($page['items'] ?? []), 'is_array'));
            array_push($responses, ...$items);
            $before = data_get(end($items) ?: [], 'token');
        } while (count($items) === $want && count($responses) < $limit);

        return array_slice($responses, 0, $limit);
    }

    /**
     * The form's questions as `field id => [title, ref]`, including the ones inside groups.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, array{title: string, ref: ?string, type: ?string}>
     */
    protected function questions(Run $run, array $config, string $form): array
    {
        $definition = (array) $this->typeform($run, $config, 'GET', "/forms/{$form}");
        $questions = [];

        $collect = function (array $fields) use (&$collect, &$questions): void {
            foreach ($fields as $field) {
                if (! is_array($field)) {
                    continue;
                }

                if (isset($field['id'])) {
                    // Typeform writes emphasis in a title as *bold*: the answer's key is the plain words.
                    $title = trim(strip_tags(str_replace('*', '', (string) ($field['title'] ?? ''))));
                    $questions[(string) $field['id']] = ['title' => $title, 'ref' => $field['ref'] ?? null, 'type' => $field['type'] ?? null];
                }

                $collect((array) data_get($field, 'properties.fields', []));
            }
        };

        $collect((array) ($definition['fields'] ?? []));

        return $questions;
    }

    /**
     * An answer's value as one plain thing: text, a number, a yes/no, the labels
     * chosen, or a link.
     *
     * @param  array<string, mixed>  $answer
     */
    protected function answerValue(array $answer): mixed
    {
        return match ($answer['type'] ?? null) {
            'choice' => $answer['choice']['label'] ?? $answer['choice']['other'] ?? null,
            'choices' => array_values(array_filter([...(array) ($answer['choices']['labels'] ?? []), ...(isset($answer['choices']['other']) ? [$answer['choices']['other']] : [])])),
            'payment' => ['amount' => data_get($answer, 'payment.amount'), 'success' => data_get($answer, 'payment.success'), 'name' => data_get($answer, 'payment.name')],
            'text', 'email', 'url', 'file_url', 'number', 'boolean', 'date', 'phone_number' => $answer[$answer['type']] ?? null,
            default => $answer[$answer['type'] ?? ''] ?? null,
        };
    }

    /**
     * A response with each answer next to its question.
     *
     * @param  array<string, mixed>  $response
     * @param  array<string, array{title: string, ref: ?string, type: ?string}>  $questions
     * @return array<string, mixed>
     */
    protected function shapeResponse(array $response, array $questions): array
    {
        $answers = [];
        $byTitle = [];
        $byRef = [];

        foreach (array_filter((array) ($response['answers'] ?? []), 'is_array') as $answer) {
            $id = (string) data_get($answer, 'field.id');
            $ref = data_get($answer, 'field.ref') ?? ($questions[$id]['ref'] ?? null);
            $title = $questions[$id]['title'] ?? $ref ?? $id;
            $value = $this->answerValue($answer);

            $answers[] = ['field_id' => $id, 'ref' => $ref, 'title' => $title, 'type' => $answer['type'] ?? null, 'value' => $value];
            $byTitle[$title] = $value;

            if ($ref !== null) {
                $byRef[$ref] = $value;
            }
        }

        return [
            'response_id' => $response['response_id'] ?? $response['token'] ?? null,
            'token' => $response['token'] ?? null,
            'completed' => isset($response['submitted_at']),
            'landed_at' => $response['landed_at'] ?? null,
            'submitted_at' => $response['submitted_at'] ?? null,
            'email' => collect($answers)->firstWhere('type', 'email')['value'] ?? null,
            'answers' => $answers,
            'answers_map' => $byTitle,
            'answers_by_ref' => $byRef,
            'hidden' => (object) ($response['hidden'] ?? []),
            'score' => data_get($response, 'calculated.score'),
            'variables' => array_values(array_filter((array) ($response['variables'] ?? []), 'is_array')),
            'browser' => data_get($response, 'metadata.browser'),
            'platform' => data_get($response, 'metadata.platform'),
            'referer' => data_get($response, 'metadata.referer'),
            'user_agent' => data_get($response, 'metadata.user_agent'),
            'network_id' => data_get($response, 'metadata.network_id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    protected function shapeForm(array $form): array
    {
        $workspace = data_get($form, 'workspace.href');

        return [
            'id' => $form['id'] ?? null,
            'title' => $form['title'] ?? null,
            'type' => $form['type'] ?? null,
            'url' => data_get($form, '_links.display'),
            'workspace_id' => is_string($workspace) ? basename($workspace) : null,
            'theme_id' => is_string(data_get($form, 'theme.href')) ? basename((string) data_get($form, 'theme.href')) : null,
            'language' => data_get($form, 'settings.language'),
            'is_public' => data_get($form, 'settings.is_public'),
            'created_at' => $form['created_at'] ?? null,
            'last_updated_at' => $form['last_updated_at'] ?? null,
            'published_at' => $form['published_at'] ?? null,
        ];
    }

    /**
     * The form picker every form node starts with.
     *
     * @return array<string, mixed>
     */
    protected function formField(string $description = 'Pick a form, or give its id or link.'): array
    {
        return Field::dynamic('Form', 'typeform.forms', $description, 'Customer survey');
    }
}
