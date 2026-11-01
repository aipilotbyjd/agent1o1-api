<?php

namespace App\Services\SuperAdmin\Pricing;

use App\Models\Billing\CreditPack;
use App\Models\Billing\CreditPackDefinition;
use App\Models\SuperAdmin\SuperAdmin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Credit pack definitions. The first write copies `config('billing.packs')`
 * into the table, because `PackCatalog` treats a non-empty table as the whole
 * catalog: without the copy, adding one new pack would silently hide the
 * three that were configured.
 */
class SuperAdminPackService
{
    public function __construct(private readonly PricingChangeLogger $log) {}

    /**
     * The packs as an admin sees them: the table once it has rows, else the
     * config fallback (flagged, and not editable until imported).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function all(): Collection
    {
        if (CreditPackDefinition::query()->exists()) {
            return CreditPackDefinition::query()->orderBy('sort_order')->orderBy('credits')->get()
                ->map(fn (CreditPackDefinition $pack): array => [...$this->present($pack), 'source' => 'database']);
        }

        $order = 0;

        return collect((array) config('billing.packs', []))->map(fn (array $pack, string $key): array => [
            'id' => null,
            'key' => $key,
            'label' => $pack['label'],
            'credits' => (int) $pack['credits'],
            'price_cents' => (int) $pack['price_cents'],
            'badge' => null,
            'is_active' => true,
            'sort_order' => $order++,
            'source' => 'config',
        ])->values();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(SuperAdmin $actor, array $data): CreditPackDefinition
    {
        return DB::transaction(function () use ($actor, $data): CreditPackDefinition {
            $this->importConfigIfEmpty($actor);

            $pack = CreditPackDefinition::query()->create([
                'key' => $data['key'],
                'label' => $data['label'],
                'credits' => $data['credits'],
                'price_cents' => $data['price_cents'],
                'badge' => $data['badge'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => ((int) CreditPackDefinition::query()->max('sort_order')) + 1,
            ]);

            $this->log->record($actor, 'pack', 'created', $pack, null, $this->present($pack), null, $pack->key);

            return $pack;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SuperAdmin $actor, CreditPackDefinition $pack, array $data, ?string $reason = null): CreditPackDefinition
    {
        return DB::transaction(function () use ($actor, $pack, $data, $reason): CreditPackDefinition {
            $before = $this->present($pack);

            $pack->fill(array_intersect_key($data, array_flip(['label', 'credits', 'price_cents', 'badge', 'is_active', 'sort_order'])));

            if (! $pack->isDirty()) {
                return $pack;
            }

            $pack->save();

            $this->log->record($actor, 'pack', 'updated', $pack, $before, $this->present($pack), $reason, $pack->key);

            return $pack;
        });
    }

    /**
     * Deletes a pack nobody has bought. A pack with purchases is kept (its
     * key is on those purchase rows) and has to be deactivated instead.
     */
    public function destroy(SuperAdmin $actor, CreditPackDefinition $pack, string $reason): void
    {
        if (CreditPack::query()->where('pack_key', $pack->key)->exists()) {
            throw new HttpException(409, "Pack [{$pack->key}] has been purchased. Deactivate it instead.");
        }

        DB::transaction(function () use ($actor, $pack, $reason): void {
            $this->log->record($actor, 'pack', 'deleted', $pack, $this->present($pack), null, $reason, $pack->key);

            $pack->delete();
        });
    }

    private function importConfigIfEmpty(SuperAdmin $actor): void
    {
        if (CreditPackDefinition::query()->exists()) {
            return;
        }

        $order = 0;

        foreach ((array) config('billing.packs', []) as $key => $pack) {
            $imported = CreditPackDefinition::query()->create([
                'key' => $key,
                'label' => $pack['label'],
                'credits' => (int) $pack['credits'],
                'price_cents' => (int) $pack['price_cents'],
                'is_active' => true,
                'sort_order' => $order++,
            ]);

            $this->log->record($actor, 'pack', 'imported', $imported, null, $this->present($imported), 'Imported from config', $imported->key);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function present(CreditPackDefinition $pack): array
    {
        return [
            'id' => $pack->id,
            'key' => $pack->key,
            'label' => $pack->label,
            'credits' => $pack->credits,
            'price_cents' => $pack->price_cents,
            'badge' => $pack->badge,
            'is_active' => $pack->is_active,
            'sort_order' => $pack->sort_order,
        ];
    }
}
