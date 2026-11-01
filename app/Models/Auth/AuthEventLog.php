<?php

namespace App\Models\Auth;

use App\Enums\Auth\AuthEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of security-relevant account activity.
 *
 * Named `AuthEventLog` rather than `AuthEvent` so it doesn't collide with the
 * `App\Enums\Auth\AuthEvent` case it stores; the table keeps the plain
 * `auth_events` name.
 *
 * Rows carry no `updated_at`: nothing ever edits one.
 */
#[Fillable(['user_id', 'email', 'event', 'ip_address', 'user_agent', 'context'])]
class AuthEventLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'auth_events';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'event' => AuthEvent::class,
            'context' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
