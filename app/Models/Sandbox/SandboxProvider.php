<?php

namespace App\Models\Sandbox;

use App\Enums\Sandbox\SandboxDriverType;
use App\Enums\Sandbox\SandboxMode;
use Database\Factories\Sandbox\SandboxProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A remote place code can run (see the migration). Credentials are
 * encrypted at rest and never serialised. A row that was never saved stands
 * for the legacy `E2B_API_KEY` fallback (see `SandboxProviders`).
 */
#[Fillable(['key', 'name', 'driver', 'is_enabled', 'is_default', 'priority', 'weight', 'modes', 'config', 'credentials', 'price_per_hour_usd', 'max_concurrent', 'notes', 'last_checked_at', 'last_check_ok', 'last_check_message', 'consecutive_failures', 'circuit_open_until', 'last_error'])]
#[Hidden(['credentials'])]
class SandboxProvider extends Model
{
    /** @use HasFactory<SandboxProviderFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
            'priority' => 'integer',
            'weight' => 'integer',
            'modes' => 'array',
            'config' => 'array',
            'credentials' => 'encrypted:array',
            'price_per_hour_usd' => 'float',
            'max_concurrent' => 'integer',
            'last_checked_at' => 'datetime',
            'last_check_ok' => 'boolean',
            'consecutive_failures' => 'integer',
            'circuit_open_until' => 'datetime',
        ];
    }

    public function type(): SandboxDriverType
    {
        return SandboxDriverType::from($this->driver);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $value = ($this->config ?? [])[$key] ?? null;

        return filled($value) ? $value : $default;
    }

    public function credential(string $key): ?string
    {
        $value = ($this->credentials ?? [])[$key] ?? null;

        return filled($value) ? (string) $value : null;
    }

    /**
     * Has everything the kind needs: an address where it has one, and the
     * credential it requires.
     */
    public function isConfigured(): bool
    {
        $required = $this->type()->requiredCredential();

        if ($required !== null && $this->credential($required) === null) {
            return false;
        }

        return ! $this->type()->usesCustomUrl() || filled($this->setting('url'));
    }

    public function isCircuitOpen(): bool
    {
        return $this->circuit_open_until !== null && $this->circuit_open_until->isFuture();
    }

    /**
     * Whether the provider may be used for `$mode`: the kind supports it and
     * the operator did not narrow it away.
     */
    public function allowsMode(SandboxMode $mode): bool
    {
        if (! $this->type()->supports($mode)) {
            return false;
        }

        return $this->modes === null || in_array($mode->value, $this->modes, true);
    }

    /**
     * Cost of `$seconds` of this provider's time, in US dollars.
     */
    public function costFor(int $seconds): float
    {
        return round(($this->price_per_hour_usd / 3600) * max($seconds, 0), 6);
    }

    /**
     * The last four characters of the main credential, for telling two keys
     * apart in a list without exposing either.
     */
    public function credentialHint(): ?string
    {
        $required = $this->type()->requiredCredential() ?? 'token';
        $value = $this->credential($required);

        return $value !== null ? '…'.substr($value, -4) : null;
    }
}
