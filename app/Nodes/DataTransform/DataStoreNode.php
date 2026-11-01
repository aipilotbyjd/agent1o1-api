<?php

namespace App\Nodes\DataTransform;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use App\Models\Workspaces\WorkspaceDataEntry;
use App\Nodes\Support\Field;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * A small memory for the workspace's workflows, kept between runs: the last
 * ID a poll saw, a running count, a list of what was already handled.
 * `claim` is the one to reach for to avoid doing something twice: it records
 * a key only the first time and says whether this run was first.
 *
 * Values never leave the workspace, are limited in size and number, and can
 * expire. This is a convenience for workflow state, not a database.
 */
class DataStoreNode implements DeclaresEffect, DeclaresOutputSchema, HasIcon, NodeContract
{
    public const int MAX_ENTRIES = 10000;

    public const int MAX_VALUE_BYTES = 65536;

    public const int MAX_LIST_ITEMS = 1000;

    private const array OPERATIONS = [
        'get' => 'Read a value',
        'set' => 'Save a value',
        'claim' => 'Check if new (save only the first time)',
        'increment' => 'Add to a number',
        'append' => 'Add to a list',
        'delete' => 'Delete a value',
        'list_keys' => 'List the saved names',
    ];

    public function type(): string
    {
        return 'data_store';
    }

    public function category(): string
    {
        return NodeCategory::DataTransform->value;
    }

    public function name(): string
    {
        return 'Data Store';
    }

    public function icon(): string
    {
        return 'database-01';
    }

    public function description(): string
    {
        return 'Remembers values between runs: the last item seen, a running total, or what you have already handled.';
    }

    public function effect(array $config): ActionEffect
    {
        return match ($config['operation'] ?? 'get') {
            'get', 'list_keys' => ActionEffect::Read,
            'delete' => ActionEffect::Destructive,
            default => ActionEffect::Write,
        };
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['operation'],
            'properties' => [
                'operation' => Field::select('Operation', self::OPERATIONS, null, 'get'),
                'key' => Field::text('Name', 'What to call it, e.g. last-order-id or processed:{{ nodes.hook.payload.id }}. Up to 255 characters.', 'last-order-id'),
                // Untyped on purpose: text, a number, true/false, a list or an object are all fine.
                'value' => array_diff_key(Field::textarea('Value', 'Save a value / Check if new / Add to a list: what to store. Text, a number, true or false, or JSON.'), ['type' => true]),
                'amount' => Field::integer('Add', 'Add to a number: how much to add (negative to subtract).', 1),
                'expires_in_days' => Field::integer('Forget after (days)', 'Optional. Remove it automatically after this long.', null, 1, 3650),
                'prefix' => Field::text('Starting with', 'List the saved names: only those starting with this.'),
                'store' => Field::advanced(Field::text('Store', 'Keep unrelated things apart by giving each its own store. Letters, numbers, - and _.', 'default')),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'value' => ['title' => 'Value', 'description' => 'The value read, or the value now saved.'],
                'found' => ['title' => 'Found', 'description' => 'Read a value: true when something was saved under that name.'],
                'is_new' => ['title' => 'Is new', 'description' => 'Check if new: true when this is the first time the name was seen.'],
                'result' => ['title' => 'Branch', 'description' => 'Check if new: new or seen. Read a value: found or missing. So an edge can follow it.'],
                'keys' => ['title' => 'Names', 'description' => 'List the saved names: the names found.'],
                'deleted' => ['title' => 'Deleted', 'description' => 'Delete: true when something was removed.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $operation = (string) ($config['operation'] ?? '');
        $store = $this->store($config);
        $workspaceId = $run->workspace_id;

        if (! isset(self::OPERATIONS[$operation])) {
            throw new InvalidArgumentException("Unsupported Data Store operation [{$operation}].");
        }

        if ($operation !== 'get' && $operation !== 'list_keys' && $operation !== 'delete') {
            $this->prune($workspaceId);
        }

        if ($operation === 'list_keys') {
            $prefix = trim((string) ($config['prefix'] ?? ''));

            $keys = WorkspaceDataEntry::query()->live()->where('workspace_id', $workspaceId)->where('store', $store)
                ->when($prefix !== '', fn ($query) => $query->whereRaw($query->getQuery()->getGrammar()->wrap('key')." like ? escape '!'", [str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $prefix).'%']))
                ->orderBy('key')->limit(1000)->pluck('key')->all();

            return ['keys' => $keys, 'count' => count($keys)];
        }

        $key = $this->key($config);

        return match ($operation) {
            'get' => $this->get($workspaceId, $store, $key),
            'set' => $this->set($workspaceId, $store, $key, $config),
            'claim' => $this->claim($workspaceId, $store, $key, $config),
            'increment' => $this->increment($workspaceId, $store, $key, $config),
            'append' => $this->append($workspaceId, $store, $key, $config),
            'delete' => ['deleted' => WorkspaceDataEntry::query()->where('workspace_id', $workspaceId)->where('store', $store)->where('key', $key)->delete() > 0],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $workspaceId, string $store, string $key): array
    {
        $entry = $this->live($workspaceId, $store, $key)->first();

        return ['value' => $entry?->value, 'found' => $entry !== null, 'result' => $entry !== null ? 'found' : 'missing'];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function set(string $workspaceId, string $store, string $key, array $config): array
    {
        $value = $this->value($config);

        $this->assertRoom($workspaceId, $store, $key);

        WorkspaceDataEntry::query()->updateOrCreate(
            ['workspace_id' => $workspaceId, 'store' => $store, 'key' => $key],
            ['value' => $value, 'expires_at' => $this->expiry($config)],
        );

        return ['value' => $value];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function claim(string $workspaceId, string $store, string $key, array $config): array
    {
        $value = array_key_exists('value', $config) && $config['value'] !== '' ? $this->value($config) : now()->toIso8601String();

        // An expired claim is free to be taken again.
        WorkspaceDataEntry::query()->where('workspace_id', $workspaceId)->where('store', $store)->where('key', $key)->where('expires_at', '<=', now())->delete();

        $this->assertRoom($workspaceId, $store, $key);

        // One atomic insert that does nothing when the name is taken: the unique
        // key (workspace, store, name) is what makes this safe when two runs
        // claim at once, and it never raises, so it is safe inside a transaction.
        $created = WorkspaceDataEntry::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'store' => $store,
            'key' => $key,
            'value' => json_encode($value),
            'expires_at' => $this->expiry($config),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($created === 0) {
            return ['is_new' => false, 'result' => 'seen', 'value' => $this->live($workspaceId, $store, $key)->first()?->value];
        }

        return ['is_new' => true, 'result' => 'new', 'value' => $value];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function increment(string $workspaceId, string $store, string $key, array $config): array
    {
        if (! is_numeric($config['amount'] ?? 1)) {
            throw new InvalidArgumentException('"Add" must be a number.');
        }

        $amount = $config['amount'] ?? 1;

        return DB::transaction(function () use ($workspaceId, $store, $key, $amount, $config): array {
            $entry = WorkspaceDataEntry::query()->where('workspace_id', $workspaceId)->where('store', $store)->where('key', $key)->lockForUpdate()->first();

            if ($entry !== null && $entry->expires_at !== null && $entry->expires_at->isPast()) {
                $entry->delete();
                $entry = null;
            }

            if ($entry !== null && ! is_numeric($entry->value)) {
                throw new RuntimeException("“{$key}” holds something that is not a number, so it cannot be added to.");
            }

            if ($entry === null) {
                $this->assertRoom($workspaceId, $store, $key);
            }

            $next = ($entry?->value ?? 0) + $amount;

            WorkspaceDataEntry::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'store' => $store, 'key' => $key],
                ['value' => $next, 'expires_at' => $entry?->expires_at ?? $this->expiry($config)],
            );

            return ['value' => $next];
        });
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function append(string $workspaceId, string $store, string $key, array $config): array
    {
        $item = $this->value($config);

        return DB::transaction(function () use ($workspaceId, $store, $key, $item, $config): array {
            $entry = WorkspaceDataEntry::query()->where('workspace_id', $workspaceId)->where('store', $store)->where('key', $key)->lockForUpdate()->first();

            if ($entry !== null && $entry->expires_at !== null && $entry->expires_at->isPast()) {
                $entry->delete();
                $entry = null;
            }

            if ($entry !== null && ! is_array($entry->value)) {
                throw new RuntimeException("“{$key}” does not hold a list, so nothing can be added to it.");
            }

            if ($entry === null) {
                $this->assertRoom($workspaceId, $store, $key);
            }

            $list = [...($entry?->value ?? []), $item];

            if (count($list) > self::MAX_LIST_ITEMS) {
                throw new RuntimeException('That list is full ('.number_format(self::MAX_LIST_ITEMS).' items). Start a new one, or remove old items.');
            }

            $this->assertSize($list);

            WorkspaceDataEntry::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'store' => $store, 'key' => $key],
                ['value' => $list, 'expires_at' => $entry?->expires_at ?? $this->expiry($config)],
            );

            return ['value' => $list, 'count' => count($list)];
        });
    }

    /**
     * @return Builder<WorkspaceDataEntry>
     */
    private function live(string $workspaceId, string $store, string $key): Builder
    {
        return WorkspaceDataEntry::query()->live()->where('workspace_id', $workspaceId)->where('store', $store)->where('key', $key);
    }

    /**
     * Clears what has expired, so it neither counts toward the limit nor lingers.
     */
    private function prune(string $workspaceId): void
    {
        WorkspaceDataEntry::query()->where('workspace_id', $workspaceId)->where('expires_at', '<=', now())->delete();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function key(array $config): string
    {
        $key = trim((string) ($config['key'] ?? ''));

        if ($key === '') {
            throw new InvalidArgumentException('Give the value a name.');
        }

        if (mb_strlen($key) > 255) {
            throw new InvalidArgumentException('The name is too long (the limit is 255 characters).');
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function store(array $config): string
    {
        $store = trim((string) ($config['store'] ?? '')) ?: 'default';

        if (preg_match('/^[A-Za-z0-9_-]{1,100}$/', $store) !== 1) {
            throw new InvalidArgumentException('A store name can only use letters, numbers, - and _ (up to 100 characters).');
        }

        return $store;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function value(array $config): mixed
    {
        $value = $config['value'] ?? null;

        $this->assertSize($value);

        return $value;
    }

    private function assertSize(mixed $value): void
    {
        if (strlen((string) json_encode($value)) > self::MAX_VALUE_BYTES) {
            throw new InvalidArgumentException('That value is too large to store (the limit is '.(self::MAX_VALUE_BYTES / 1024).' KB).');
        }
    }

    private function assertRoom(string $workspaceId, string $store, string $key): void
    {
        $exists = WorkspaceDataEntry::query()->where('workspace_id', $workspaceId)->where('store', $store)->where('key', $key)->exists();

        if (! $exists && WorkspaceDataEntry::query()->live()->where('workspace_id', $workspaceId)->count() >= self::MAX_ENTRIES) {
            throw new RuntimeException('This workspace’s Data Store is full ('.number_format(self::MAX_ENTRIES).' values). Delete some, or let them expire.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function expiry(array $config): ?\DateTimeInterface
    {
        return filled($config['expires_in_days'] ?? null) ? now()->addDays(max(1, (int) $config['expires_in_days'])) : null;
    }
}
