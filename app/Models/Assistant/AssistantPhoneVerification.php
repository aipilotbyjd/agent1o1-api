<?php

namespace App\Models\Assistant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A code texted to prove a number belongs to the member. Only its hash is
 * kept.
 */
#[Fillable(['user_id', 'phone', 'code_hash', 'attempts', 'expires_at'])]
class AssistantPhoneVerification extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $hidden = ['code_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
