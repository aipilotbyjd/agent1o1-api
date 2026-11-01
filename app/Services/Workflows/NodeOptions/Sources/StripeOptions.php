<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The account's customers, products, prices, subscriptions and coupons, newest
 * first. Customers and products are searched on Stripe's side once the member has
 * typed three letters (its search needs that many); anything else is filtered from
 * the page that came back. A Connect platform's list is the connected account's
 * when the node names one.
 *
 * A page is followed by the id of its last record (`starting_after`), or, in a
 * search, by the `page` token Stripe hands back.
 */
class StripeOptions extends HttpOptionsSource
{
    private const string API = 'https://api.stripe.com/v1';

    private const int PAGE_SIZE = 50;

    /**
     * Currencies Stripe counts in whole units, not hundredths.
     */
    private const array ZERO_DECIMAL = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

    public function sources(): array
    {
        return ['stripe.customers', 'stripe.products', 'stripe.prices', 'stripe.subscriptions', 'stripe.coupons'];
    }

    protected function appName(): string
    {
        return 'Stripe';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        $account = $query->configString('stripe_account');

        return Http::withToken((string) $query->token)
            ->withHeaders($account !== null && preg_match('/^acct_[A-Za-z0-9]+$/', $account) === 1 ? ['Stripe-Account' => $account] : [])
            ->acceptJson()
            ->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $resource = substr($source, strlen('stripe.'));
        $search = $query->search !== null && mb_strlen($query->search) >= 3 && in_array($resource, ['customers', 'products'], true) ? $this->searchClause($resource, $query->search) : null;

        $body = $search !== null
            ? $this->getJson($query, self::API."/{$resource}/search", ['query' => $search, 'limit' => self::PAGE_SIZE, 'page' => $query->cursor])
            : $this->getJson($query, self::API."/{$resource}", [...$this->filters($resource, $query), 'limit' => self::PAGE_SIZE, 'starting_after' => $this->safeId($query->cursor)]);

        $records = array_values(array_filter((array) ($body['data'] ?? []), fn (mixed $record): bool => is_array($record) && is_string($record['id'] ?? null)));
        $options = collect($records)->map(fn (array $record): NodeOption => $this->option($resource, $record));

        if ($search === null) {
            $options = $options->filter(fn (NodeOption $option): bool => $query->matches($option->label, (string) $option->value));
        }

        $more = (bool) ($body['has_more'] ?? false);
        $next = $search !== null ? ($body['next_page'] ?? null) : ($records === [] ? null : end($records)['id']);

        return new NodeOptionsPage($options, $more && is_string($next) ? $next : null);
    }

    /**
     * What to ask of a list endpoint: only what can be chosen, with the names
     * that are shown next to ids.
     *
     * @return array<string, mixed>
     */
    private function filters(string $resource, NodeOptionsQuery $query): array
    {
        return match ($resource) {
            'products' => ['active' => 'true'],
            'prices' => array_filter([
                'active' => 'true',
                'expand' => ['data.product'],
                'product' => preg_match('/^prod_[A-Za-z0-9_]+$/', (string) $query->configString('product_id')) === 1 ? $query->configString('product_id') : null,
            ]),
            'subscriptions' => ['expand' => ['data.customer']],
            default => [],
        };
    }

    /**
     * A Stripe search clause for what was typed: a part of the name (or email).
     */
    private function searchClause(string $resource, string $typed): string
    {
        $text = str_replace(['"', '\\'], '', $typed);

        return $resource === 'customers' ? "name~\"{$text}\" OR email~\"{$text}\"" : "name~\"{$text}\"";
    }

    /**
     * A cursor is the id of a record we handed out; anything else starts over.
     */
    private function safeId(?string $cursor): ?string
    {
        return $cursor !== null && preg_match('/^[A-Za-z0-9_-]{1,100}$/', $cursor) === 1 ? $cursor : null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function option(string $resource, array $record): NodeOption
    {
        $id = (string) $record['id'];

        return match ($resource) {
            'customers' => new NodeOption($id, (string) ($record['name'] ?? $record['email'] ?? $id), $record['email'] ?? $id),
            'products' => new NodeOption($id, (string) ($record['name'] ?? $id), $id),
            'prices' => new NodeOption($id, trim((string) (data_get($record, 'product.name') ?? $record['nickname'] ?? $id).' · '.$this->priceText($record)), $id),
            'subscriptions' => new NodeOption(
                $id,
                (string) (data_get($record, 'customer.name') ?? data_get($record, 'customer.email') ?? (is_string($record['customer'] ?? null) ? $record['customer'] : $id)),
                trim(($record['status'] ?? '').' · '.$id, ' ·'),
            ),
            default => new NodeOption($id, (string) ($record['name'] ?? $id), $this->couponText($record)),
        };
    }

    /**
     * "19.99 USD / month", the way a person reads a price.
     *
     * @param  array<string, mixed>  $price
     */
    private function priceText(array $price): string
    {
        if (! isset($price['unit_amount'])) {
            return 'custom amount';
        }

        $currency = strtolower((string) ($price['currency'] ?? ''));
        $amount = in_array($currency, self::ZERO_DECIMAL, true) ? (string) $price['unit_amount'] : number_format($price['unit_amount'] / 100, 2, '.', '');
        $interval = data_get($price, 'recurring.interval');

        return $amount.' '.strtoupper($currency).(is_string($interval) ? " / {$interval}" : '');
    }

    /**
     * "20% off, once" or "5.00 USD off, forever".
     *
     * @param  array<string, mixed>  $coupon
     */
    private function couponText(array $coupon): string
    {
        $off = isset($coupon['percent_off']) ? rtrim(rtrim((string) $coupon['percent_off'], '0'), '.').'% off' : (isset($coupon['amount_off']) ? number_format($coupon['amount_off'] / 100, 2, '.', '').' '.strtoupper((string) ($coupon['currency'] ?? '')).' off' : '');

        return trim($off.', '.($coupon['duration'] ?? ''), ', ');
    }
}
