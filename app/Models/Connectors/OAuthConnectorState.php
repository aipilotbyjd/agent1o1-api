<?php

namespace App\Models\Connectors;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A short-lived CSRF-state row for an in-progress OAuth authorization-code
 * flow: created by `OAuthConnectorFlowService::initiate()`, consumed and
 * deleted by `handleCallback()`. Never holds a token itself.
 */
#[Fillable(['workspace_id', 'user_id', 'connector_id', 'connector_credential_id', 'state', 'name', 'redirect_uri', 'scope', 'expires_at'])]
class OAuthConnectorState extends Model
{
    use HasUuids;

    protected $table = 'oauth_connector_states';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }
}
