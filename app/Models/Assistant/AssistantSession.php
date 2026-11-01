<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantSessionOrigin;
use App\Enums\Assistant\AssistantSessionStatus;
use Database\Factories\Assistant\AssistantSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One conversation with an `Assistant`, from any channel. `origin` +
 * `external_thread_ref` let a Slack thread, email thread or SMS chat find
 * its way back to the same session. Incognito sessions expire after a day.
 */
#[Fillable(['assistant_id', 'assistant_trigger_id', 'title', 'status', 'origin', 'external_thread_ref', 'channel_context', 'incognito', 'expires_at', 'last_activity_at'])]
class AssistantSession extends Model
{
    /** @use HasFactory<AssistantSessionFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'origin' => 'web',
        'incognito' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssistantSessionStatus::class,
            'origin' => AssistantSessionOrigin::class,
            'channel_context' => 'array',
            'incognito' => 'boolean',
            'expires_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(AssistantTrigger::class, 'assistant_trigger_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(AssistantTurn::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AssistantAction::class);
    }

    public function queuedInputs(): HasMany
    {
        return $this->hasMany(AssistantQueuedInput::class);
    }

    /**
     * Sessions that haven't expired: incognito ones disappear from every
     * list the moment `expires_at` passes, before the purge job deletes them.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAlive(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * The cloud computer this conversation runs code on, once it has.
     */
    public function sandbox(): HasOne
    {
        return $this->hasOne(AssistantSandbox::class);
    }
}
