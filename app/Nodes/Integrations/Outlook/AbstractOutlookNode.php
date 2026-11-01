<?php

namespace App\Nodes\Integrations\Outlook;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request/error handling for the Outlook node family: mail and
 * calendar through Microsoft Graph, with the account's token resolved by
 * `ResolvesConnectorCredential` like every other integration.
 */
abstract class AbstractOutlookNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public const string BASE_URL = 'https://graph.microsoft.com/v1.0';

    public function category(): string
    {
        return 'outlook';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function get(Run $run, string $endpoint, array $config, array $query = []): array
    {
        return $this->call($run, 'get', $endpoint, $config, $query);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function post(Run $run, string $endpoint, array $config, array $body = []): array
    {
        return $this->call($run, 'post', $endpoint, $config, $body);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function patch(Run $run, string $endpoint, array $config, array $body = []): array
    {
        return $this->call($run, 'patch', $endpoint, $config, $body);
    }

    /**
     * An id from a setting, ready to sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function pathId(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        return $value !== '' ? rawurlencode($value) : throw new InvalidArgumentException("{$label} is required.");
    }

    /**
     * A required text setting, trimmed.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function requiredText(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        return $value !== '' ? $value : throw new InvalidArgumentException("{$label} is required.");
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function delete(Run $run, string $endpoint, array $config): array
    {
        return $this->call($run, 'delete', $endpoint, $config, []);
    }

    /**
     * Graph answers sends, replies and deletes with an empty 202/204, so
     * those come back as `['ok' => true]`.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(Run $run, string $method, string $endpoint, array $config, array $params): array
    {
        $request = $this->request($this->resolveAccessToken($run, $config));

        $response = match ($method) {
            'get' => $request->get(self::BASE_URL.$endpoint, $params),
            'delete' => $request->delete(self::BASE_URL.$endpoint),
            'patch' => $request->asJson()->patch(self::BASE_URL.$endpoint, $params),
            default => $request->asJson()->post(self::BASE_URL.$endpoint, $params),
        };

        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new RuntimeException("Outlook API error [{$endpoint}]: {$message}");
        }

        return $response->json() ?? ['ok' => true];
    }

    /**
     * Plain-text bodies and UTC times, so results read the same as Gmail's
     * and Google Calendar's.
     */
    private function request(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->withHeaders(['Prefer' => 'outlook.body-content-type="text", outlook.timezone="UTC"'])
            ->timeout(30);
    }

    /**
     * "a@x.test, B <b@y.test>" → Graph recipients.
     *
     * @return list<array{emailAddress: array{address: string}}>
     */
    protected function recipients(?string $addresses): array
    {
        return collect(preg_split('/[,;]/', (string) $addresses) ?: [])
            ->map(fn (string $address): string => trim(preg_match('/<([^>]+)>/', $address, $match) ? $match[1] : $address))
            ->filter()
            ->map(fn (string $address): array => ['emailAddress' => ['address' => $address]])
            ->values()
            ->all();
    }
}
