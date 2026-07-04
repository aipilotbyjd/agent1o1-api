<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id',
    'user_id',
    'credential_type_key',
    'state',
    'name',
    'scopes',
    'redirect_uri',
    'expires_at',
])]
class OAuthCredentialState extends Model
{
    use HasUuids;

    // Without this, Eloquent infers "o_auth_credential_states" (it splits
    // "OAuth" into "o_auth"), which does not match the migration's table.
    protected $table = 'oauth_credential_states';

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
