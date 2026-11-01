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
 * The folders of a Zoho Mail account (the connection's account id, else the first
 * account the login sees). Mail can't search them, so a search filters what came back.
 */
class ZohoMailOptions extends HttpOptionsSource
{
    public function sources(): array
    {
        return ['zoho_mail.folders'];
    }

    protected function appName(): string
    {
        return 'Zoho Mail';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$this->login($query)['token']])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $base = $this->login($query)['base'];
        $account = trim((string) ($query->credentialData['account_id'] ?? ''));

        if (preg_match('/^\d+$/', $account) !== 1) {
            $account = (string) ($this->getJson($query, $base.'/accounts')['data'][0]['accountId'] ?? '');
        }

        if ($account === '') {
            throw new ConnectorException('Zoho Mail did not show an account for this login.');
        }

        $folders = collect($this->getJson($query, $base."/accounts/{$account}/folders")['data'] ?? [])
            ->filter(fn (mixed $folder): bool => is_array($folder) && isset($folder['folderId']) && $query->matches((string) ($folder['folderName'] ?? '')));

        return new NodeOptionsPage($folders->map(fn (array $folder): NodeOption => new NodeOption((string) $folder['folderId'], (string) ($folder['folderName'] ?? $folder['folderId']))));
    }

    /**
     * @return array{token: string, base: string}
     *
     * @throws ConnectorException
     */
    private function login(NodeOptionsQuery $query): array
    {
        try {
            return app(CrmAccessTokens::class)->zohoApp($query->credentialData, 'mail');
        } catch (InvalidArgumentException|RuntimeException $exception) {
            throw new ConnectorException($exception->getMessage());
        }
    }
}
