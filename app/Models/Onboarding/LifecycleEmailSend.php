<?php

namespace App\Models\Onboarding;

use App\Enums\Onboarding\LifecycleEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One lifecycle email sent to one user: see `LifecycleEmails`.
 */
#[Fillable(['user_id', 'email', 'sent_at'])]
class LifecycleEmailSend extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email' => LifecycleEmail::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
