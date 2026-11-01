<?php

namespace App\Models\Credentials;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'provider', 'provider_id', 'avatar'])]
class OAuthConnection extends Model
{
    use HasFactory, HasUuids;

    /**
     * Set explicitly: the convention would snake-case this class to
     * `o_auth_connections`, but the table has always been `oauth_connections`.
     */
    protected $table = 'oauth_connections';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
