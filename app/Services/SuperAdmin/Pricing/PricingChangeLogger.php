<?php

namespace App\Services\SuperAdmin\Pricing;

use App\Models\Billing\Plan;
use App\Models\Billing\PricingChangeLog;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes a pricing change to both histories: the readable before/after log
 * the pricing screens show, and the tamper-evident audit chain.
 */
class PricingChangeLogger
{
    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        SuperAdmin $actor,
        string $subjectType,
        string $action,
        ?Model $subject,
        ?array $before,
        ?array $after,
        ?string $reason = null,
        ?string $subjectKey = null,
        bool $global = false,
    ): PricingChangeLog {
        $log = PricingChangeLog::query()->create([
            'plan_id' => ! $global && $subject instanceof Plan ? $subject->id : null,
            'subject_type' => $subjectType,
            'subject_key' => $subjectKey ?? ($subject instanceof Plan ? $subject->slug : (string) $subject?->getKey()),
            'super_admin_id' => $actor->id,
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
        ]);

        $this->audit->record($actor, "pricing.{$subjectType}.{$action}", $subject, $before, $after, $reason);

        return $log;
    }
}
