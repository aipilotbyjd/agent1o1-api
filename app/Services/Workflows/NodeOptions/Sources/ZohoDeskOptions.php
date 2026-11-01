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
 * The departments and the active agents of a Zoho Desk organization. Desk can't
 * search them, so a search filters what came back.
 */
class ZohoDeskOptions extends HttpOptionsSource
{
    public function sources(): array
    {
        return ['zoho_desk.departments', 'zoho_desk.agents'];
    }

    protected function appName(): string
    {
        return 'Zoho Desk';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        $org = trim((string) ($query->credentialData['org_id'] ?? ''));

        if (preg_match('/^\d+$/', $org) !== 1) {
            throw new ConnectorException('Add the Zoho Desk organization id to the connection.');
        }

        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$this->login($query)['token'], 'orgId' => $org])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $base = $this->login($query)['base'];

        if ($source === 'zoho_desk.agents') {
            $agents = collect($this->getJson($query, $base.'/agents', ['status' => 'ACTIVE', 'limit' => 100])['data'] ?? [])
                ->filter(fn (mixed $agent): bool => is_array($agent) && isset($agent['id']) && $query->matches(trim(($agent['firstName'] ?? '').' '.($agent['lastName'] ?? '')), (string) ($agent['emailId'] ?? '')));

            return new NodeOptionsPage($agents->map(fn (array $agent): NodeOption => new NodeOption((string) $agent['id'], trim(($agent['firstName'] ?? '').' '.($agent['lastName'] ?? '')) ?: (string) ($agent['name'] ?? $agent['id']), $agent['emailId'] ?? null)));
        }

        $departments = collect($this->getJson($query, $base.'/departments', ['limit' => 100])['data'] ?? [])
            ->filter(fn (mixed $department): bool => is_array($department) && isset($department['id']) && $query->matches((string) ($department['name'] ?? '')));

        return new NodeOptionsPage($departments->map(fn (array $department): NodeOption => new NodeOption((string) $department['id'], (string) ($department['name'] ?? $department['id']))));
    }

    /**
     * @return array{token: string, base: string}
     *
     * @throws ConnectorException
     */
    private function login(NodeOptionsQuery $query): array
    {
        try {
            return app(CrmAccessTokens::class)->zohoApp($query->credentialData, 'desk');
        } catch (InvalidArgumentException|RuntimeException $exception) {
            throw new ConnectorException($exception->getMessage());
        }
    }
}
