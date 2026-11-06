<?php

namespace App\Models\Ai;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-click AI provider connection in flight (see `AiProviderConnectFlow`):
 * the unguessable `state` the provider hands back, and the PKCE
 * `code_verifier` (encrypted, never sent anywhere but the provider's token
 * endpoint) that proves the code was asked for by us. Claimed by deleting
 * it, so each one completes a connection at most once. Ones nobody came
 * back from are cleared when the next connection starts.
 */
#[Fillable(['workspace_id', 'user_id', 'execution_provider', 'scope', 'state', 'code_verifier', 'redirect_uri', 'expires_at'])]
#[Hidden(['code_verifier'])]
class AiProviderConnectState extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => ConnectorCredentialScope::class,
            'code_verifier' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
