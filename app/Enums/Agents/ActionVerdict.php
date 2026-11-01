<?php

namespace App\Enums\Agents;

/**
 * What `ActionGate` decided for one tool call. Also the vocabulary of tool
 * rules and guardrails (`allow`/`ask`/`deny`): `Simulate` is only ever the
 * gate's own outcome, for Test run and for contexts that can't pause.
 * Declared from most to least permissive; `strictest()` picks the later one.
 */
enum ActionVerdict: string
{
    case Allow = 'allow';
    case Simulate = 'simulate';
    case Ask = 'ask';
    case Deny = 'deny';

    public function rank(): int
    {
        return array_search($this, self::cases(), true);
    }

    public static function strictest(self ...$verdicts): self
    {
        return array_reduce($verdicts, fn (self $carry, self $verdict): self => $verdict->rank() > $carry->rank() ? $verdict : $carry, self::Allow);
    }

    /**
     * The values a tool rule or guardrail may use.
     *
     * @return list<string>
     */
    public static function ruleValues(): array
    {
        return [self::Allow->value, self::Ask->value, self::Deny->value];
    }
}
