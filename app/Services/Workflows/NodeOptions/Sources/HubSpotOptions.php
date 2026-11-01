<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Support\Collection;

/**
 * The account's owners, and the deal and ticket pipelines with their stages. HubSpot
 * takes the private app token as a bearer token and can't search, so a search
 * filters what came back. A stage list follows the pipeline picked, else shows every
 * stage as "Pipeline: Stage".
 */
class HubSpotOptions extends HttpOptionsSource
{
    private const string API = 'https://api.hubapi.com/crm/v3';

    public function sources(): array
    {
        return ['hubspot.owners', 'hubspot.deal_pipelines', 'hubspot.deal_stages', 'hubspot.ticket_pipelines', 'hubspot.ticket_stages'];
    }

    protected function appName(): string
    {
        return 'HubSpot';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        if ($source === 'hubspot.owners') {
            $body = $this->getJson($query, self::API.'/owners', ['limit' => 100, 'after' => $query->cursor]);
            $owners = collect($body['results'] ?? [])->filter(fn (mixed $owner): bool => is_array($owner) && isset($owner['id']))
                ->map(fn (array $owner): array => ['id' => (string) $owner['id'], 'name' => trim(($owner['firstName'] ?? '').' '.($owner['lastName'] ?? '')) ?: (string) ($owner['email'] ?? $owner['id']), 'email' => $owner['email'] ?? null])
                ->filter(fn (array $owner): bool => $query->matches($owner['name'], (string) $owner['email']));

            return new NodeOptionsPage(
                $owners->map(fn (array $owner): NodeOption => new NodeOption($owner['id'], $owner['name'], $owner['email'])),
                data_get($body, 'paging.next.after') !== null ? (string) data_get($body, 'paging.next.after') : null,
            );
        }

        $object = str_starts_with($source, 'hubspot.ticket') ? 'tickets' : 'deals';
        $pipelines = collect($this->getJson($query, self::API."/pipelines/{$object}")['results'] ?? [])->filter(fn (mixed $pipeline): bool => is_array($pipeline) && isset($pipeline['id']));

        if (str_ends_with($source, '_pipelines')) {
            return new NodeOptionsPage($pipelines->filter(fn (array $pipeline): bool => $query->matches((string) ($pipeline['label'] ?? '')))->map(fn (array $pipeline): NodeOption => new NodeOption((string) $pipeline['id'], (string) ($pipeline['label'] ?? $pipeline['id']))));
        }

        $chosen = trim((string) $query->configString('pipeline_id'));
        $single = $chosen !== '' && $pipelines->contains(fn (array $pipeline): bool => (string) $pipeline['id'] === $chosen);

        return new NodeOptionsPage($this->stages($pipelines->when($single, fn (Collection $all): Collection => $all->filter(fn (array $pipeline): bool => (string) $pipeline['id'] === $chosen)), $query, ! $single));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $pipelines
     * @return Collection<int, NodeOption>
     */
    private function stages(Collection $pipelines, NodeOptionsQuery $query, bool $prefix): Collection
    {
        return $pipelines->flatMap(fn (array $pipeline): array => array_map(
            fn (array $stage): NodeOption => new NodeOption((string) $stage['id'], ($prefix ? ($pipeline['label'] ?? $pipeline['id']).': ' : '').($stage['label'] ?? $stage['id'])),
            array_values(array_filter((array) ($pipeline['stages'] ?? []), fn (mixed $stage): bool => is_array($stage) && isset($stage['id']) && $query->matches((string) ($stage['label'] ?? '')))),
        ))->values();
    }
}
