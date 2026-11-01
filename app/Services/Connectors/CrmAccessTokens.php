<?php

namespace App\Services\Connectors;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Short-lived access tokens for the CRMs whose login is a client id and secret
 * (Salesforce) or a client id, secret and refresh token (Zoho), not one long-lived
 * key. A token is asked for when needed and kept until just before it expires, so
 * a flow that makes many calls asks once.
 *
 * Salesforce uses the client-credentials flow of an External Client App on the
 * org's My Domain; Zoho exchanges the refresh token of a "Self Client" at the
 * data center the account lives in.
 */
class CrmAccessTokens
{
    /**
     * Zoho's accounts server and API root for each data center.
     *
     * @var array<string, array{accounts: string, api: string}>
     */
    public const array ZOHO_REGIONS = [
        'us' => ['accounts' => 'https://accounts.zoho.com', 'api' => 'https://www.zohoapis.com'],
        'eu' => ['accounts' => 'https://accounts.zoho.eu', 'api' => 'https://www.zohoapis.eu'],
        'in' => ['accounts' => 'https://accounts.zoho.in', 'api' => 'https://www.zohoapis.in'],
        'au' => ['accounts' => 'https://accounts.zoho.com.au', 'api' => 'https://www.zohoapis.com.au'],
        'jp' => ['accounts' => 'https://accounts.zoho.jp', 'api' => 'https://www.zohoapis.jp'],
        'ca' => ['accounts' => 'https://accounts.zohocloud.ca', 'api' => 'https://www.zohoapis.ca'],
        'sa' => ['accounts' => 'https://accounts.zoho.sa', 'api' => 'https://www.zohoapis.sa'],
    ];

    /**
     * A Salesforce org's My Domain host, checked so a token request can only go to Salesforce.
     *
     * @throws InvalidArgumentException
     */
    public function salesforceHost(string $domain): string
    {
        $host = strtolower(trim((string) preg_replace('~^https?://~i', '', trim($domain)), " /"));

        return preg_match('/^[a-z0-9][a-z0-9-]*(\.[a-z0-9-]+)*\.(my\.salesforce\.com|my\.salesforce-setup\.com|sandbox\.my\.salesforce\.com|salesforce\.com)$/', $host) === 1
            ? $host
            : throw new InvalidArgumentException('The Salesforce domain must be your My Domain, like acme.my.salesforce.com.');
    }

    /**
     * A Salesforce access token and the instance URL to call.
     *
     * @param  array<string, mixed>  $data  the connection's fields: domain, client_id, api_key (the client secret)
     * @return array{token: string, instance: string}
     *
     * @throws RuntimeException
     */
    public function salesforce(array $data): array
    {
        $host = $this->salesforceHost((string) ($data['domain'] ?? ''));
        $id = trim((string) ($data['client_id'] ?? ''));
        $secret = trim((string) ($data['api_key'] ?? ''));

        if ($id === '' || $secret === '') {
            throw new InvalidArgumentException('Connect Salesforce: its client id and client secret are needed.');
        }

        $token = $this->cached('salesforce', [$host, $id, $secret], fn (): array => $this->request("https://{$host}/services/oauth2/token", ['grant_type' => 'client_credentials', 'client_id' => $id, 'client_secret' => $secret], 'Salesforce'), 3300);

        $instance = (string) ($token['instance_url'] ?? '');

        if (! str_starts_with($instance, 'https://') || ! str_ends_with((string) parse_url($instance, PHP_URL_HOST), 'salesforce.com')) {
            throw new RuntimeException('Salesforce answered with an instance address that is not Salesforce.');
        }

        return ['token' => (string) $token['access_token'], 'instance' => rtrim($instance, '/')];
    }

    /**
     * The domain each Zoho data center's apps live under (desk.zoho.eu, mail.zoho.eu...).
     *
     * @var array<string, string>
     */
    public const array ZOHO_DOMAINS = ['us' => 'zoho.com', 'eu' => 'zoho.eu', 'in' => 'zoho.in', 'au' => 'zoho.com.au', 'jp' => 'zoho.jp', 'ca' => 'zohocloud.ca', 'sa' => 'zoho.sa'];

    /**
     * A Zoho access token and the base URL of one of the other Zoho apps for the account's data center.
     * The same refresh token serves every app it was given the scopes of.
     *
     * @param  array<string, mixed>  $data  the connection's fields
     * @param  string  $app  desk, books, projects, campaigns, mail or cliq
     * @return array{token: string, base: string}
     *
     * @throws InvalidArgumentException
     */
    public function zohoApp(array $data, string $app): array
    {
        $login = $this->zoho($data);
        $region = strtolower(trim((string) ($data['region'] ?? ''))) ?: 'us';
        $domain = self::ZOHO_DOMAINS[$region];

        $base = match ($app) {
            'desk' => "https://desk.{$domain}/api/v1",
            'books' => $login['api'].'/books/v3',
            'projects' => "https://projectsapi.{$domain}/api/v3",
            'campaigns' => "https://campaigns.{$domain}/api/v1.1",
            'mail' => "https://mail.{$domain}/api",
            'cliq' => "https://cliq.{$domain}/api/v2",
            default => throw new InvalidArgumentException("\"{$app}\" is not a Zoho app."),
        };

        return ['token' => $login['token'], 'base' => $base];
    }

    /**
     * A Zoho access token and the API root for the account's data center.
     *
     * @param  array<string, mixed>  $data  the connection's fields: region, client_id, api_key (the client secret), refresh_token
     * @return array{token: string, api: string}
     *
     * @throws RuntimeException
     */
    public function zoho(array $data): array
    {
        $region = strtolower(trim((string) ($data['region'] ?? ''))) ?: 'us';
        $id = trim((string) ($data['client_id'] ?? ''));
        $secret = trim((string) ($data['api_key'] ?? ''));
        $refresh = trim((string) ($data['refresh_token'] ?? ''));

        if (! isset(self::ZOHO_REGIONS[$region])) {
            throw new InvalidArgumentException('The Zoho region must be one of '.implode(', ', array_keys(self::ZOHO_REGIONS)).'.');
        }

        if ($id === '' || $secret === '' || $refresh === '') {
            throw new InvalidArgumentException('Connect Zoho: its client id, client secret and refresh token are needed.');
        }

        $token = $this->cached('zoho', [$region, $id, $secret, $refresh], fn (): array => $this->request(self::ZOHO_REGIONS[$region]['accounts'].'/oauth/v2/token', ['grant_type' => 'refresh_token', 'client_id' => $id, 'client_secret' => $secret, 'refresh_token' => $refresh], 'Zoho'), 3300);

        return ['token' => (string) $token['access_token'], 'api' => self::ZOHO_REGIONS[$region]['api']];
    }

    /**
     * @param  array<int, string>  $parts  what the token is for; they make the cache key, hashed
     * @param  callable(): array<string, mixed>  $fetch
     * @return array<string, mixed>
     */
    private function cached(string $app, array $parts, callable $fetch, int $seconds): array
    {
        return Cache::remember("crm-token:{$app}:".hash('sha256', implode('|', $parts)), $seconds, $fetch);
    }

    /**
     * @param  array<string, string>  $form
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    private function request(string $url, array $form, string $app): array
    {
        try {
            $response = Http::asForm()->acceptJson()->timeout(20)->post($url, $form);
        } catch (ConnectionException) {
            throw new RuntimeException("Couldn't reach {$app} to log in. Try again in a moment.");
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            $reason = (string) ($response->json('error_description') ?? $response->json('error') ?? "HTTP {$response->status()}");

            throw new RuntimeException("{$app} refused the login: {$reason}. Check the connection's fields.");
        }

        return (array) $response->json();
    }
}
