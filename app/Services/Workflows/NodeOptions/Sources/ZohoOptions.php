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
 * The modules you can create records in, and the active users. Zoho's login is a
 * client id, secret and refresh token traded for a short-lived access token (kept
 * for the hour) that is used at the data center's own address. It can't search, so a
 * search filters what came back.
 */
class ZohoOptions extends HttpOptionsSource
{
    public function sources(): array
    {
        return ['zoho.modules', 'zoho.users'];
    }

    protected function appName(): string
    {
        return 'Zoho CRM';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$this->login($query)['token']])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $base = $this->login($query)['api'].'/crm/v7';

        if ($source === 'zoho.users') {
            $users = collect($this->getJson($query, $base.'/users', ['type' => 'ActiveUsers'])['users'] ?? [])
                ->filter(fn (mixed $user): bool => is_array($user) && isset($user['id']) && $query->matches((string) ($user['full_name'] ?? ''), (string) ($user['email'] ?? '')));

            return new NodeOptionsPage($users->map(fn (array $user): NodeOption => new NodeOption((string) $user['id'], (string) ($user['full_name'] ?? $user['id']), $user['email'] ?? null)));
        }

        $modules = collect($this->getJson($query, $base.'/settings/modules')['modules'] ?? [])
            ->filter(fn (mixed $module): bool => is_array($module) && isset($module['api_name']) && ($module['api_supported'] ?? false) && ($module['creatable'] ?? false) && $query->matches((string) $module['api_name'], (string) ($module['plural_label'] ?? '')));

        return new NodeOptionsPage($modules->map(fn (array $module): NodeOption => new NodeOption((string) $module['api_name'], (string) ($module['plural_label'] ?? $module['api_name']), ($module['generated_type'] ?? 'default') === 'custom' ? 'Custom' : null)));
    }

    /**
     * @return array{token: string, api: string}
     *
     * @throws ConnectorException
     */
    private function login(NodeOptionsQuery $query): array
    {
        try {
            return app(CrmAccessTokens::class)->zoho($query->credentialData);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            throw new ConnectorException($exception->getMessage());
        }
    }
}
