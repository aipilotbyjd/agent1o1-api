<?php

namespace App\Models\Ai;

use Database\Factories\Ai\AiEndpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An OpenAI-compatible endpoint the operator runs or uses (see the
 * migration). The key is encrypted at rest and never serialised.
 */
#[Fillable(['slug', 'name', 'base_url', 'api_key', 'is_enabled', 'workspace_requests_per_minute', 'total_requests_per_minute', 'notes', 'last_checked_at', 'last_check_ok', 'last_check_message'])]
#[Hidden(['api_key'])]
class AiEndpoint extends Model
{
    /** @use HasFactory<AiEndpointFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_enabled' => 'boolean',
            'workspace_requests_per_minute' => 'integer',
            'total_requests_per_minute' => 'integer',
            'last_checked_at' => 'datetime',
            'last_check_ok' => 'boolean',
        ];
    }

    /**
     * The `config/ai.php` provider entry this endpoint stands for. A server
     * that takes no key still gets a placeholder, since a provider with an
     * empty key counts as not configured.
     *
     * @return array{driver: string, url: string, key: string}
     */
    public function providerConfig(): array
    {
        return [
            'driver' => 'openai-compatible',
            'url' => $this->base_url,
            'key' => $this->api_key ?? 'none',
        ];
    }

    /**
     * The per-minute limits, or null when the endpoint has none.
     *
     * @return array{workspace: ?int, total: ?int}|null
     */
    public function rateLimits(): ?array
    {
        if ($this->workspace_requests_per_minute === null && $this->total_requests_per_minute === null) {
            return null;
        }

        return ['workspace' => $this->workspace_requests_per_minute, 'total' => $this->total_requests_per_minute];
    }

    /**
     * The last four characters of the key, for telling two keys apart in a
     * list without exposing either.
     */
    public function keyHint(): ?string
    {
        return filled($this->api_key) ? '…'.substr($this->api_key, -4) : null;
    }
}
