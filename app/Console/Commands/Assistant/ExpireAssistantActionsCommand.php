<?php

namespace App\Console\Commands\Assistant;

use App\Enums\Assistant\AssistantActionStatus;
use App\Models\Assistant\AssistantAction;
use App\Models\Assistant\AssistantTurn;
use App\Services\Assistant\Runtime\AssistantLoop;
use Illuminate\Console\Command;

/**
 * Expires assistant actions nobody decided in time. An expired call counts
 * as declined: its turn resumes and the assistant tells the owner it
 * didn't go ahead: a paused turn never waits forever.
 */
class ExpireAssistantActionsCommand extends Command
{
    protected $signature = 'assistant:expire-actions';

    protected $description = 'Expire assistant actions nobody approved in time and resume their turns.';

    public function handle(AssistantLoop $loop): int
    {
        $turnIds = AssistantAction::query()
            ->where('status', AssistantActionStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->pluck('assistant_turn_id')
            ->unique();

        AssistantAction::query()
            ->whereIn('assistant_turn_id', $turnIds)
            ->where('status', AssistantActionStatus::Pending)
            ->where('expires_at', '<=', now())
            ->update(['status' => AssistantActionStatus::Expired, 'decided_at' => now(), 'updated_at' => now()]);

        AssistantTurn::query()->whereKey($turnIds)->get()->each(fn (AssistantTurn $turn) => $loop->resumeIfDecided($turn));

        $this->info("Expired actions on {$turnIds->count()} turn(s).");

        return self::SUCCESS;
    }
}
