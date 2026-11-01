<?php

namespace App\Services\SuperAdmin\Alerts\Evaluators;

use App\Enums\Notifications\AlertSeverity;
use App\Models\SuperAdmin\AlertRule;
use App\Services\SuperAdmin\Alerts\AlertEvaluator;
use App\Services\SuperAdmin\Alerts\AlertSignal;
use App\Services\SuperAdmin\Audit\AuditChainVerifier;
use Illuminate\Support\Facades\Cache;

/**
 * Walking the whole audit chain every minute would not scale, so the result is
 * cached for `cache_minutes`. The daily `super-admin:audit-verify` still runs
 * its own full check.
 */
class AuditChainBroken implements AlertEvaluator
{
    public function __construct(private readonly AuditChainVerifier $verifier) {}

    public static function type(): string
    {
        return 'audit_chain_broken';
    }

    public static function defaults(): array
    {
        return ['cache_minutes' => 60];
    }

    public function evaluate(AlertRule $rule): array
    {
        $result = Cache::remember('super-admin-alerts:audit-chain', now()->addMinutes((int) $rule->setting('cache_minutes')), fn (): array => $this->verifier->verify());

        if ($result['intact']) {
            return [];
        }

        return [new AlertSignal('audit-chain', 'The Super Admin audit log failed its integrity check: '.$result['break']['message'], ['sequence' => $result['break']['sequence'], 'reason' => $result['break']['reason']], AlertSeverity::Critical)];
    }
}
