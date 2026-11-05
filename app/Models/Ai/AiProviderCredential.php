<?php

namespace App\Models\Ai;

use App\Enums\Ai\AiProviderAuthMethod;
use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Concerns\HasScopedVisibility;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Ai\AiProviderCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A workspace's own API key for one AI provider (bring your own key). It
 * covers every catalog model routed through `execution_provider` (the same
 * string `model_routes.execution_provider` and `config/ai.php` use) never
 * a single model. `ByokProviderRegistrar` puts a valid key in front of the
 * platform's own key whenever a call is made on the workspace's behalf.
 *
 * `data` (`{api_key}`) is encrypted at rest and `#[Hidden]`; `key_hint` is
 * the masked form the UI shows instead. A credential from one-click connect
 * with an expiring OAuth token (`auth_method` = `oauth`) keeps the current
 * access token in `api_key`, so every caller uses it like any key, and its
 * `refresh_token` beside it: `AiProviderTokenRefresher` renews it before
 * `token_expires_at`. Personal/Team visibility and the
 * per-group default work exactly as for `ConnectorCredential`: see
 * `HasScopedVisibility`.
 */
#[Fillable(['workspace_id', 'created_by', 'execution_provider', 'scope', 'is_default', 'name', 'data', 'auth_method', 'token_expires_at', 'key_hint', 'validation_status', 'validation_message', 'last_validated_at', 'last_used_at'])]
#[Hidden(['data'])]
class AiProviderCredential extends Model
{
    /** @use HasFactory<AiProviderCredentialFactory> */
    use HasFactory, HasScopedVisibility, HasUuids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'scope' => 'team',
        'auth_method' => 'api_key',
        'is_default' => false,
        'validation_status' => 'unvalidated',
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
            'auth_method' => AiProviderAuthMethod::class,
            'token_expires_at' => 'datetime',
            'validation_status' => AiProviderCredentialStatus::class,
            'last_validated_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function apiKey(): string
    {
        return (string) ($this->data['api_key'] ?? '');
    }

    public function refreshToken(): ?string
    {
        $refreshToken = $this->data['refresh_token'] ?? null;

        return is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : null;
    }

    public function isOAuth(): bool
    {
        return $this->auth_method === AiProviderAuthMethod::OAuth;
    }

    /**
     * Whether this is an OAuth token that has expired, or will within
     * `$seconds`, and so should be refreshed before it is used.
     */
    public function tokenExpiresWithin(int $seconds): bool
    {
        return $this->isOAuth()
            && $this->token_expires_at !== null
            && $this->token_expires_at->lte(now()->addSeconds($seconds));
    }

    public function isValid(): bool
    {
        return $this->validation_status === AiProviderCredentialStatus::Valid;
    }

    /**
     * The key with all but its edges masked, e.g. `sk-p…a1b2`.
     */
    public static function hintFor(string $apiKey): string
    {
        return strlen($apiKey) <= 12
            ? '…'.substr($apiKey, -4)
            : substr($apiKey, 0, 4).'…'.substr($apiKey, -4);
    }

    protected function defaultGroupColumn(): string
    {
        return 'execution_provider';
    }
}
