<?php

namespace App\Models\Connectors;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Concerns\HasScopedVisibility;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Connectors\ConnectorCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A workspace's stored secret for a `Connector`: an OAuth token pair or a
 * manually-entered API key/bearer token/basic-auth pair, depending on the
 * owning `Connector::auth_type`. `data` is encrypted at rest via Laravel's
 * `encrypted:array` cast and is `#[Hidden]` so it never round-trips through
 * `toArray()`/`toJson()` by accident: `ConnectorCredentialResource` must
 * still be relied on for API responses, this is a second guard, not the
 * only one.
 *
 * `scope` + `is_default` back Gumloop's Personal/Team credentials and
 * default-account resolution: see `HasScopedVisibility` and
 * `Nodes\Integrations\Concerns\ResolvesConnectorCredential`.
 */
#[Fillable(['workspace_id', 'connector_id', 'created_by', 'scope', 'is_default', 'name', 'account_label', 'data', 'last_used_at', 'expires_at'])]
#[Hidden(['data'])]
class ConnectorCredential extends Model
{
    /** @use HasFactory<ConnectorCredentialFactory> */
    use HasFactory, HasScopedVisibility, HasUuids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'scope' => 'team',
        'is_default' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => ConnectorCredentialScope::class,
            'is_default' => 'boolean',
            'data' => 'encrypted:array',
            'last_used_at' => 'datetime',
            'last_tested_at' => 'datetime',
            'last_test_ok' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether the stored access token is past its expiry. An expired token
     * that can be refreshed is still usable: see `isUsable()`.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * An OAuth connection holding a refresh token renews itself
     * (`ConnectorTokens`), so its access token expiring doesn't end it,
     * unless the provider has rejected that refresh token (revoked access,
     * a changed password).
     */
    public function canRefresh(): bool
    {
        return filled($this->data['refresh_token'] ?? null)
            && blank($this->data['refresh_rejected_at'] ?? null)
            && ($this->connector?->isOAuth() ?? false);
    }

    /**
     * Whether the connection can be used right now, refreshing if needed:
     * what "connected" means to anyone choosing an account.
     */
    public function isUsable(): bool
    {
        return ! $this->isExpired() || $this->canRefresh();
    }

    protected function defaultGroupColumn(): string
    {
        return 'connector_id';
    }
}
