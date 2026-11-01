<?php

namespace App\Enums\Agents;

/**
 * How freely an agent may act on its own: see
 * `Services\Agents\Approvals\ActionGate` for what each mode does to a call.
 * Declared from strictest to most permissive; `rank()` follows that order,
 * which is what `strictest()` compares.
 */
enum AutonomyMode: string
{
    /** Reads only; every write is refused. */
    case ReadOnly = 'read_only';

    /** Reads run; every write waits for a person. */
    case Ask = 'ask';

    /** Writes are blocked until a proposed plan is approved; then its steps run. */
    case Plan = 'plan';

    /** A reviewer model judges each write: low risk runs, anything else asks. */
    case Smart = 'smart';

    /** Everything runs, except destructive actions, which still ask. */
    case Autopilot = 'autopilot';

    public function rank(): int
    {
        return array_search($this, self::cases(), true);
    }

    public function isStricterThan(self $other): bool
    {
        return $this->rank() < $other->rank();
    }

    public static function strictest(?self ...$modes): ?self
    {
        $modes = array_filter($modes);

        if ($modes === []) {
            return null;
        }

        return array_reduce($modes, fn (?self $carry, self $mode): self => $carry === null || $mode->isStricterThan($carry) ? $mode : $carry);
    }

    public function label(): string
    {
        return match ($this) {
            self::ReadOnly => 'Read-only',
            self::Ask => 'Ask',
            self::Plan => 'Plan',
            self::Smart => 'Smart',
            self::Autopilot => 'Autopilot',
        };
    }
}
