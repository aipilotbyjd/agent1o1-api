<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Services\Connectors\CrmAccessTokens;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * The org's objects you can create records of, and its active users. Salesforce's
 * login is a client id and secret that are traded for a short-lived session on the
 * org's own address, so each call asks for that first (the session is kept for the
 * hour). It can't search, so a search filters what came back.
 */
class SalesforceOptions extends HttpOptionsSource
{
    private const string VERSION = 'v62.0';

    public function sources(): array
    {
        return ['salesforce.objects', 'salesforce.users'];
    }

    protected function appName(): string
    {
        return 'Salesforce';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withToken($this->login($query)['token'])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $base = $this->login($query)['instance'].'/services/data/'.self::VERSION;

        if ($source === 'salesforce.users') {
            $users = collect($this->getJson($query, $base.'/query', ['q' => "SELECT Id, Name, Email FROM User WHERE IsActive = true AND UserType = 'Standard' ORDER BY Name LIMIT 200"])['records'] ?? [])
                ->filter(fn (mixed $user): bool => is_array($user) && isset($user['Id']) && $query->matches((string) ($user['Name'] ?? ''), (string) ($user['Email'] ?? '')));

            return new NodeOptionsPage($users->map(fn (array $user): NodeOption => new NodeOption((string) $user['Id'], (string) ($user['Name'] ?? $user['Id']), $user['Email'] ?? null)));
        }

        $objects = collect($this->getJson($query, $base.'/sobjects')['sobjects'] ?? [])
            ->filter(fn (mixed $object): bool => is_array($object) && isset($object['name']) && ($object['createable'] ?? false) && ($object['queryable'] ?? false) && $query->matches((string) $object['name'], (string) ($object['label'] ?? '')));

        return new NodeOptionsPage($objects->map(fn (array $object): NodeOption => new NodeOption((string) $object['name'], (string) ($object['label'] ?? $object['name']), ($object['custom'] ?? false) ? 'Custom' : null)));
    }

    /**
     * @return array{token: string, instance: string}
     *
     * @throws ConnectorException
     */
    private function login(NodeOptionsQuery $query): array
    {
        try {
            return app(CrmAccessTokens::class)->salesforce($query->credentialData);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            throw new ConnectorException($exception->getMessage());
        }
    }
}
