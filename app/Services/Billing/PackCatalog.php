<?php

namespace App\Services\Billing;

use App\Models\Billing\CreditPackDefinition;

/**
 * The credit packs on sale. Once an admin has defined any pack in
 * `credit_pack_definitions` that table is the whole catalog (so deactivating
 * the last one really empties the shop); until then `config('billing.packs')`
 * is used, which keeps an untouched install behaving exactly as before.
 *
 * Each pack is returned in the shape the config uses.
 */
class PackCatalog
{
    /**
     * Packs that can be bought right now, keyed by pack key.
     *
     * @return array<string, array{label: string, credits: int, price_cents: int, badge: string|null}>
     */
    public function sellable(): array
    {
        if (! CreditPackDefinition::query()->exists()) {
            return collect((array) config('billing.packs', []))
                ->map(fn (array $pack): array => [
                    'label' => (string) $pack['label'],
                    'credits' => (int) $pack['credits'],
                    'price_cents' => (int) $pack['price_cents'],
                    'badge' => null,
                ])
                ->all();
        }

        return CreditPackDefinition::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('credits')
            ->get()
            ->mapWithKeys(fn (CreditPackDefinition $pack): array => [$pack->key => [
                'label' => $pack->label,
                'credits' => $pack->credits,
                'price_cents' => $pack->price_cents,
                'badge' => $pack->badge,
            ]])
            ->all();
    }

    /**
     * @return array{label: string, credits: int, price_cents: int, badge: string|null}|null
     */
    public function find(string $key): ?array
    {
        return $this->sellable()[$key] ?? null;
    }
}
