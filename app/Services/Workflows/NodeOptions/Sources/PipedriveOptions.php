<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The account's users and pipelines, and the stages of the chosen pipeline (or of
 * all of them, as "Pipeline: Stage"). Pipedrive takes the API token in a header and
 * can't search, so a search filters what came back.
 */
class PipedriveOptions extends HttpOptionsSource
{
    private const string API = 'https://api.pipedrive.com/v1';

    public function sources(): array
    {
        return ['pipedrive.users', 'pipedrive.pipelines', 'pipedrive.stages'];
    }

    protected function appName(): string
    {
        return 'Pipedrive';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['x-api-token' => (string) $query->token])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        if ($source === 'pipedrive.stages') {
            $pipeline = trim((string) $query->configString('pipeline_id'));
            $names = collect($this->getJson($query, self::API.'/pipelines')['data'] ?? [])->filter(fn (mixed $row): bool => is_array($row) && isset($row['id']))->mapWithKeys(fn (array $row): array => [(string) $row['id'] => (string) ($row['name'] ?? $row['id'])]);
            $stages = collect($this->getJson($query, self::API.'/stages', preg_match('/^\d+$/', $pipeline) === 1 ? ['pipeline_id' => $pipeline] : [])['data'] ?? [])
                ->filter(fn (mixed $stage): bool => is_array($stage) && isset($stage['id']) && $query->matches((string) ($stage['name'] ?? '')));

            return new NodeOptionsPage($stages->map(fn (array $stage): NodeOption => new NodeOption((string) $stage['id'], (preg_match('/^\d+$/', $pipeline) === 1 ? '' : ($names[(string) ($stage['pipeline_id'] ?? '')] ?? '').': ').($stage['name'] ?? $stage['id']))));
        }

        $users = $source === 'pipedrive.users';
        $items = collect($this->getJson($query, self::API.($users ? '/users' : '/pipelines'))['data'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']) && ($users ? ($item['active_flag'] ?? true) : true) && $query->matches((string) ($item['name'] ?? ''), (string) ($item['email'] ?? '')));

        return new NodeOptionsPage($items->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], (string) ($item['name'] ?? $item['id']), $users ? ($item['email'] ?? null) : null)));
    }
}
