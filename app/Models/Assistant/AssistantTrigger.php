<?php

namespace App\Models\Assistant;

use App\Casts\Timezone;
use App\Enums\Assistant\AssistantTriggerStatus;
use App\Enums\Assistant\AssistantTriggerType;
use Carbon\CarbonInterface;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Something that starts the assistant on its own: a schedule (cron in the
 * owner's timezone), a one-time run, or a webhook URL. Each firing is a new
 * conversation running `prompt`.
 */
#[Fillable(['assistant_id', 'type', 'name', 'prompt', 'cron', 'timezone', 'run_at', 'next_run_at', 'webhook_token', 'status', 'created_by', 'consecutive_failures', 'last_run_at'])]
class AssistantTrigger extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'timezone' => 'UTC',
        'created_by' => 'owner',
        'consecutive_failures' => 0,
    ];

    /**
     * The webhook token is a credential: anyone with the URL can fire it.
     *
     * @var list<string>
     */
    protected $hidden = ['webhook_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AssistantTriggerType::class,
            'status' => AssistantTriggerStatus::class,
            'timezone' => Timezone::class,
            'run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'consecutive_failures' => 'integer',
        ];
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AssistantSession::class);
    }

    /**
     * When a schedule next fires after `$after`, computed in the owner's
     * timezone (so "9:00 every weekday" means their 9:00) and stored in UTC.
     */
    public function nextRunAfter(CarbonInterface $after): ?Carbon
    {
        return match ($this->type) {
            AssistantTriggerType::Schedule => Carbon::instance(
                (new CronExpression((string) $this->cron))->getNextRunDate($after->copy()->tz($this->timezone)->toDateTime(), 0, false, $this->timezone),
            )->utc(),
            AssistantTriggerType::Once => $this->run_at,
            AssistantTriggerType::Webhook => null,
        };
    }
}
