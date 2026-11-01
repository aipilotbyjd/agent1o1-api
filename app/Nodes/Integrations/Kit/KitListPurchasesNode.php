<?php

namespace App\Nodes\Integrations\Kit;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class KitListPurchasesNode extends AbstractKitNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'kit_list_purchases';
    }

    public function name(): string
    {
        return 'Kit: List Purchases';
    }

    public function description(): string
    {
        return 'Lists recorded purchases, newest first. As a trigger it starts the flow for each new purchase.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $purchases = $this->listAll($run, $config, '/purchases', 'purchases', [], $this->limit($config, 50));

        return ['purchases' => array_map(fn (array $purchase): array => ['id' => $purchase['id'] ?? null, 'transaction_id' => $purchase['transaction_id'] ?? null, 'status' => $purchase['status'] ?? null, 'email' => $purchase['email_address'] ?? null, 'currency' => $purchase['currency'] ?? null, 'total' => $purchase['total'] ?? null, 'transaction_time' => $purchase['transaction_time'] ?? null, 'products' => array_values(array_map(fn (array $product): string => (string) ($product['name'] ?? ''), array_filter((array) ($purchase['products'] ?? []), 'is_array')))], $purchases), 'count' => count($purchases)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'purchases';
    }

    public function triggerName(): string
    {
        return 'Kit: New purchase';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a purchase is recorded in Kit.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 200];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
