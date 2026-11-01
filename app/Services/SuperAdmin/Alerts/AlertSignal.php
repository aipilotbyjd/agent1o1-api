<?php

namespace App\Services\SuperAdmin\Alerts;

use App\Enums\Notifications\AlertSeverity;

/**
 * One thing a rule found wrong. `$fingerprint` names the subject (a queue, a
 * workspace, a webhook event) so the same problem on the same subject is one
 * alert, not many; `$severity` overrides the rule's own when it is graver.
 */
final readonly class AlertSignal
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $fingerprint,
        public string $title,
        public array $context = [],
        public ?AlertSeverity $severity = null,
    ) {}
}
