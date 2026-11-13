<?php

namespace App\Services\SuperAdmin\Sandbox;

use App\Enums\Sandbox\SandboxDriverType;
use App\Enums\Sandbox\SandboxMode;
use App\Models\Assistant\AssistantSandbox;
use App\Models\Sandbox\SandboxProvider;
use App\Models\Sandbox\SandboxRun;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\Workspaces\Workspace;
use App\Services\Sandbox\SandboxHealth;
use App\Services\Sandbox\SandboxManager;
use App\Services\Sandbox\SandboxProviders;
use App\Services\Sandbox\SandboxRunLog;
use App\Services\Sandbox\SandboxSettings;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Managing where code runs. Credentials are stored encrypted and never
 * appear anywhere: the audit log records only *that* they were set or
 * changed. Every change is written to the audit log with its reason.
 */
class SuperAdminSandboxService
{
    public function __construct(
        private readonly SuperAdminAuditLogger $audit,
        private readonly SandboxProviders $providers,
        private readonly SandboxManager $manager,
        private readonly SandboxHealth $health,
        private readonly SandboxSettings $settings,
        private readonly SandboxRunLog $log,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(SuperAdmin $actor, array $data, string $reason): SandboxProvider
    {
        $data['credentials'] = $this->clean($data['credentials'] ?? []) ?: null;
        $data['config'] = $this->clean($data['config'] ?? []) ?: null;
        $data['is_enabled'] = $data['is_enabled'] ?? false;

        return DB::transaction(function () use ($actor, $data, $reason): SandboxProvider {
            $provider = SandboxProvider::query()->create($data);

            $this->audit->record($actor, 'sandbox.provider.created', $provider, null, $this->snapshot($provider), $reason);

            return $provider;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SuperAdmin $actor, SandboxProvider $provider, array $data, string $reason): SandboxProvider
    {
        $clear = (bool) ($data['clear_credentials'] ?? false);
        $incoming = $this->clean($data['credentials'] ?? []);
        unset($data['clear_credentials'], $data['credentials']);

        if (array_key_exists('config', $data)) {
            $data['config'] = [...($provider->config ?? []), ...$this->clean($data['config'])];
        }

        if ($clear) {
            $data['credentials'] = null;
        } elseif ($incoming !== []) {
            $data['credentials'] = [...($provider->credentials ?? []), ...$incoming];
        }

        return DB::transaction(function () use ($actor, $provider, $data, $reason, $clear, $incoming): SandboxProvider {
            $before = $this->snapshot($provider);

            $provider->fill($data)->save();

            $after = [...$this->snapshot($provider), 'credentials_changed' => $clear || $incoming !== []];

            $this->audit->record($actor, 'sandbox.provider.updated', $provider, $before, $after, $reason);

            return $provider;
        });
    }

    /**
     * A provider that still hosts a conversation's computer cannot be
     * deleted: switch it off, or wait until the computers are gone.
     */
    public function delete(SuperAdmin $actor, SandboxProvider $provider, string $reason): void
    {
        if (AssistantSandbox::query()->where('provider', $provider->key)->exists()) {
            throw new HttpException(409, 'Conversations still have computers on this provider. Switch it off instead, or run sandbox:cleanup first.');
        }

        DB::transaction(function () use ($actor, $provider, $reason): void {
            $this->audit->record($actor, 'sandbox.provider.deleted', $provider, $this->snapshot($provider), null, $reason);

            $provider->delete();
        });
    }

    /**
     * Runs a tiny program on the provider and keeps the outcome. A success
     * also closes an open circuit.
     *
     * @return array{ok: bool, message: string, latency_ms: int}
     */
    public function test(SuperAdmin $actor, SandboxProvider $provider): array
    {
        $started = microtime(true);

        if (! $provider->isConfigured()) {
            $result = ['ok' => false, 'message' => 'Not set up: a credential or address is missing.'];
        } else {
            try {
                $this->manager->driver($provider)->check();
                $result = ['ok' => true, 'message' => 'The provider ran a test program.'];
                $this->health->succeeded($provider);
            } catch (Throwable $e) {
                $result = ['ok' => false, 'message' => Str::limit($e->getMessage(), 300, '')];
            }
        }

        $result['latency_ms'] = (int) round((microtime(true) - $started) * 1000);

        if ($provider->exists) {
            $provider->forceFill([
                'last_checked_at' => now(),
                'last_check_ok' => $result['ok'],
                'last_check_message' => Str::limit($result['message'], 500, ''),
            ])->save();
        }

        $this->audit->record($actor, 'sandbox.provider.tested', $provider->exists ? $provider : null, null, ['key' => $provider->key, 'ok' => $result['ok']]);

        return $result;
    }

    /**
     * Makes `$key` the provider tried first (null: none is, and priority decides).
     */
    public function setDefault(SuperAdmin $actor, ?string $key, string $reason): void
    {
        DB::transaction(function () use ($actor, $key, $reason): void {
            $before = SandboxProvider::query()->where('is_default', true)->value('key');

            SandboxProvider::query()->where('is_default', true)->update(['is_default' => false]);

            if ($key !== null) {
                SandboxProvider::query()->where('key', $key)->update(['is_default' => true]);
            }

            $this->audit->record($actor, 'sandbox.default.changed', null, ['default' => $before], ['default' => $key], $reason);
        });
    }

    /**
     * Gives providers priorities 10, 20, 30… in the order given. Providers
     * left out go after them.
     *
     * @param  list<string>  $order
     */
    public function reorder(SuperAdmin $actor, array $order, string $reason): void
    {
        DB::transaction(function () use ($actor, $order, $reason): void {
            $before = SandboxProvider::query()->orderBy('priority')->pluck('key')->all();

            foreach ($order as $index => $key) {
                SandboxProvider::query()->where('key', $key)->update(['priority' => ($index + 1) * 10]);
            }

            SandboxProvider::query()->whereNotIn('key', $order)->update(['priority' => (count($order) + 1) * 10]);

            $this->audit->record($actor, 'sandbox.order.changed', null, ['order' => $before], ['order' => $order], $reason);
        });
    }

    /**
     * @param  array{kill_switch?: bool, monthly_budget_usd?: ?float}  $data
     */
    public function updateSettings(SuperAdmin $actor, array $data, string $reason): void
    {
        $before = $this->settings->snapshot();

        foreach (['kill_switch', 'monthly_budget_usd'] as $key) {
            if (array_key_exists($key, $data)) {
                $this->settings->set($key, $data[$key]);
            }
        }

        $this->audit->record($actor, 'sandbox.settings.updated', null, $before, $this->settings->snapshot(), $reason);
    }

    /**
     * Everything the settings screen needs in one call.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $budget = $this->settings->monthlyBudgetUsd();
        $month = $this->log->monthCostUsd();

        return [
            'settings' => $this->settings->snapshot(),
            'month' => [
                'cost_usd' => round($month, 4),
                'budget_usd' => $budget,
                'budget_used_ratio' => $budget !== null && $budget > 0 ? round($month / $budget, 4) : null,
            ],
            'drivers' => collect(SandboxDriverType::cases())->map(fn (SandboxDriverType $type): array => [
                'driver' => $type->value,
                'label' => $type->label(),
                'modes' => collect(SandboxMode::cases())->filter(fn ($mode): bool => $type->supports($mode))->map->value->values()->all(),
                'uses_custom_url' => $type->usesCustomUrl(),
                'config_fields' => array_keys($type->configRules()),
                'credential_fields' => array_keys($type->credentialRules()),
                'required_credential' => $type->requiredCredential(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function usage(int $days): array
    {
        $since = now()->subDays($days - 1)->startOfDay();
        $runs = SandboxRun::query()->where('created_at', '>=', $since);

        $byProvider = (clone $runs)
            ->selectRaw("provider, count(*) as runs, sum(seconds) as seconds, sum(cost_usd) as cost_usd, sum(case when status = 'failed' then 1 else 0 end) as failed")
            ->groupBy('provider')->orderByDesc('seconds')->get()
            ->map(fn ($row): array => [
                'provider' => $row->provider,
                'runs' => (int) $row->runs,
                'seconds' => (int) $row->seconds,
                'cost_usd' => round((float) $row->cost_usd, 4),
                'failed' => (int) $row->failed,
            ])->all();

        $byDay = (clone $runs)
            ->selectRaw('date(created_at) as day, count(*) as runs, sum(seconds) as seconds, sum(cost_usd) as cost_usd')
            ->groupBy('day')->orderBy('day')->get()
            ->map(fn ($row): array => [
                'day' => (string) $row->day,
                'runs' => (int) $row->runs,
                'seconds' => (int) $row->seconds,
                'cost_usd' => round((float) $row->cost_usd, 4),
            ])->all();

        $top = (clone $runs)
            ->whereNotNull('workspace_id')
            ->selectRaw('workspace_id, count(*) as runs, sum(seconds) as seconds, sum(cost_usd) as cost_usd')
            ->groupBy('workspace_id')->orderByDesc('seconds')->limit(10)->get();

        $names = Workspace::query()->whereIn('id', $top->pluck('workspace_id'))->pluck('name', 'id');

        return [
            'days' => $days,
            'by_provider' => $byProvider,
            'by_day' => $byDay,
            'top_workspaces' => $top->map(fn ($row): array => [
                'workspace_id' => $row->workspace_id,
                'name' => $names[$row->workspace_id] ?? null,
                'runs' => (int) $row->runs,
                'seconds' => (int) $row->seconds,
                'cost_usd' => round((float) $row->cost_usd, 4),
            ])->all(),
        ];
    }

    /**
     * @return Collection<int, SandboxProvider>
     */
    public function list(): Collection
    {
        return $this->providers->all();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function clean(array $values): array
    {
        return array_filter($values, fn (mixed $value): bool => filled($value));
    }

    /**
     * What the audit log may keep: everything except the credentials.
     *
     * @return array<string, mixed>
     */
    private function snapshot(SandboxProvider $provider): array
    {
        return [
            'key' => $provider->key,
            'name' => $provider->name,
            'driver' => $provider->driver,
            'is_enabled' => $provider->is_enabled,
            'is_default' => $provider->is_default,
            'priority' => $provider->priority,
            'weight' => $provider->weight,
            'modes' => $provider->modes,
            'config' => $provider->config,
            'price_per_hour_usd' => $provider->price_per_hour_usd,
            'max_concurrent' => $provider->max_concurrent,
            'has_credentials' => filled($provider->credentials),
        ];
    }
}
