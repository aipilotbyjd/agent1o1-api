<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The account's marketing lists, dynamic templates and verified senders.
 * SendGrid takes the key as a bearer token and can't search, so one page is
 * fetched and a search filters it.
 */
class SendGridOptions extends HttpOptionsSource
{
    private const string API = 'https://api.sendgrid.com/v3';

    public function sources(): array
    {
        return ['sendgrid.lists', 'sendgrid.templates', 'sendgrid.senders'];
    }

    protected function appName(): string
    {
        return 'SendGrid';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withToken((string) $query->token)->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        if ($source === 'sendgrid.senders') {
            $senders = collect($this->getJson($query, self::API.'/verified_senders')['results'] ?? [])
                ->filter(fn (mixed $sender): bool => is_array($sender) && isset($sender['from_email']) && ($sender['verified'] ?? true) && $query->matches((string) ($sender['from_name'] ?? ''), (string) $sender['from_email']));

            return new NodeOptionsPage($senders->map(fn (array $sender): NodeOption => new NodeOption((string) $sender['from_email'], (string) $sender['from_email'], $sender['from_name'] ?? null)));
        }

        $templates = $source === 'sendgrid.templates';
        $body = $this->getJson($query, self::API.($templates ? '/templates' : '/marketing/lists'), $templates ? ['generations' => 'dynamic', 'page_size' => 200] : ['page_size' => 1000]);
        $items = collect($body['result'] ?? $body['templates'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']) && $query->matches((string) ($item['name'] ?? '')));

        return new NodeOptionsPage($items->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], $item['name'] ?? null, isset($item['contact_count']) ? $item['contact_count'].' contacts' : null)));
    }
}
