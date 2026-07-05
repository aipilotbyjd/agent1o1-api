<?php

namespace App\Services\Billing;

use App\Enums\CreditTransactionType;
use App\Exceptions\Billing\InsufficientCreditsException;
use App\Models\CreditPack;
use App\Models\CreditTransaction;
use App\Models\UsagePeriod;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CreditService
{
    private const REDIS_PREFIX = 'credits:';

    public function getAvailable(Workspace $workspace): int
    {
        try {
            $cached = Redis::get(self::REDIS_PREFIX.$workspace->id);

            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (\Exception $e) {
            Log::warning('Redis unavailable in getAvailable, falling back to DB.', ['error' => $e->getMessage()]);
        }

        return $this->getAvailableFromDb($workspace);
    }

    public function getAvailableFromDb(Workspace $workspace): int
    {
        $period = $workspace->currentPeriod;

        if (! $period) {
            return 0;
        }

        $remaining = $period->creditsRemaining();

        return $period->isUnlimited() ? PHP_INT_MAX : $remaining;
    }

    public function checkCredits(Workspace $workspace, int $needed): void
    {
        // No active usage period → metering isn't configured for this workspace;
        // allow the action (mirrors consume(), which no-ops without a period).
        if (! $workspace->currentPeriod) {
            return;
        }

        $available = $this->getAvailable($workspace);

        if ($available !== PHP_INT_MAX && $available < $needed) {
            throw new InsufficientCreditsException($needed, $available);
        }
    }

    public function consume(Workspace $workspace, int $cost, Model $subject): void
    {
        $period = $workspace->currentPeriod;

        if (! $period) {
            return;
        }

        DB::transaction(function () use ($workspace, $period, $cost, $subject) {
            // Pessimistic lock prevents concurrent executions from racing past the
            // availability check and over-drawing credits.
            $lockedPeriod = UsagePeriod::lockForUpdate()->find($period->id);

            if (! $lockedPeriod) {
                return;
            }

            // Idempotency: skip if a usage row already exists for this subject.
            $exists = CreditTransaction::where('usage_period_id', $lockedPeriod->id)
                ->where('type', CreditTransactionType::Usage)
                ->where('subject_type', get_class($subject))
                ->where('subject_id', $subject->getKey())
                ->exists();

            if ($exists) {
                return;
            }

            // Re-check availability under the row lock to prevent TOCTOU races.
            if (! $lockedPeriod->isUnlimited()) {
                $remaining = $lockedPeriod->creditsRemaining();

                if ($remaining < $cost) {
                    throw new InsufficientCreditsException($cost, $remaining);
                }
            }

            CreditTransaction::create([
                'workspace_id' => $workspace->id,
                'usage_period_id' => $lockedPeriod->id,
                'type' => CreditTransactionType::Usage,
                'credits' => -$cost,
                'subject_type' => get_class($subject),
                'subject_id' => $subject->getKey(),
                'created_at' => now(),
            ]);

            $lockedPeriod->increment('credits_used', $cost);
            $lockedPeriod->increment('executions_total');
        });

        try {
            Redis::decrby(self::REDIS_PREFIX.$workspace->id, $cost);
        } catch (\Exception $e) {
            Log::warning('Redis DECRBY failed after consume, queuing syncRedis.', ['workspace' => $workspace->id]);
        }
    }

    public function refund(Workspace $workspace, Model $subject): void
    {
        $period = $workspace->currentPeriod;

        if (! $period) {
            return;
        }

        $original = CreditTransaction::where('usage_period_id', $period->id)
            ->where('type', CreditTransactionType::Usage)
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey())
            ->first();

        if (! $original) {
            return;
        }

        // Idempotency: never refund the same subject twice (mirrors consume()).
        $alreadyRefunded = CreditTransaction::where('usage_period_id', $period->id)
            ->where('type', CreditTransactionType::Refund)
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey())
            ->exists();

        if ($alreadyRefunded) {
            return;
        }

        $cost = abs($original->credits);

        DB::transaction(function () use ($workspace, $period, $cost, $subject) {
            CreditTransaction::create([
                'workspace_id' => $workspace->id,
                'usage_period_id' => $period->id,
                'type' => CreditTransactionType::Refund,
                'credits' => $cost,
                'subject_type' => get_class($subject),
                'subject_id' => $subject->getKey(),
                'created_at' => now(),
            ]);

            $period->decrement('credits_used', $cost);
        });

        try {
            Redis::incrby(self::REDIS_PREFIX.$workspace->id, $cost);
        } catch (\Exception $e) {
            Log::warning('Redis INCRBY failed after refund.', ['workspace' => $workspace->id]);
        }
    }

    public function depositPack(CreditPack $pack): void
    {
        $workspace = $pack->workspace;
        $period = $workspace->currentPeriod;

        if (! $period) {
            return;
        }

        DB::transaction(function () use ($workspace, $period, $pack) {
            CreditTransaction::create([
                'workspace_id' => $workspace->id,
                'usage_period_id' => $period->id,
                'type' => CreditTransactionType::PackPurchase,
                'credits' => $pack->credits_amount,
                'description' => "Credit pack '{$pack->pack_key}' purchased.",
                'subject_type' => CreditPack::class,
                'subject_id' => $pack->id,
                'created_at' => now(),
            ]);

            $period->increment('credits_from_packs', $pack->credits_amount);
        });

        try {
            Redis::incrby(self::REDIS_PREFIX.$workspace->id, $pack->credits_amount);
        } catch (\Exception $e) {
            Log::warning('Redis INCRBY failed after pack deposit.', ['workspace' => $workspace->id]);
        }
    }

    public function adjust(Workspace $workspace, int $delta, string $reason, User $admin): void
    {
        $period = $workspace->currentPeriod;

        if (! $period) {
            return;
        }

        DB::transaction(function () use ($workspace, $period, $delta, $reason, $admin) {
            CreditTransaction::create([
                'workspace_id' => $workspace->id,
                'usage_period_id' => $period->id,
                'type' => CreditTransactionType::Adjustment,
                'credits' => $delta,
                'description' => $reason,
                'subject_type' => User::class,
                'subject_id' => $admin->id,
                'created_at' => now(),
            ]);

            if ($delta < 0) {
                $period->increment('credits_used', abs($delta));
            } elseif ($delta > 0) {
                // Positive grants must raise the available balance. creditsRemaining()
                // is (limit + packs + rolled_over - used), so a grant that only wrote a
                // transaction row — without touching any of those columns — had zero
                // effect. Land it in credits_from_packs, the general "extra credits"
                // bucket that adds to availability (and carries over like a purchase).
                $period->increment('credits_from_packs', $delta);
            }
        });

        $this->syncRedis($workspace);
    }

    public function syncRedis(Workspace $workspace): void
    {
        $available = $this->getAvailableFromDb($workspace);
        $value = $available === PHP_INT_MAX ? PHP_INT_MAX : $available;

        try {
            Redis::set(self::REDIS_PREFIX.$workspace->id, $value);
        } catch (\Exception $e) {
            Log::error('Redis SET failed in syncRedis.', ['workspace' => $workspace->id]);
        }
    }
}
