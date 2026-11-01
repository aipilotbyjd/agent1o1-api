<?php

namespace App\Models\Assistant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The cloud computer a conversation runs code on: one per conversation,
 * so files made earlier in the chat are still there. The provider deletes
 * it after a while idle; the next run starts a fresh one.
 */
#[Fillable(['assistant_id', 'assistant_session_id', 'provider', 'provider_sandbox_id', 'access_token', 'last_used_at', 'seconds_used'])]
class AssistantSandbox extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $hidden = ['access_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'last_used_at' => 'datetime',
            'seconds_used' => 'integer',
        ];
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AssistantSession::class, 'assistant_session_id');
    }
}
