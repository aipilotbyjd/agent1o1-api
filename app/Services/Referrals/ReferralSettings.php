<?php

namespace App\Services\Referrals;

use App\Enums\Referrals\ReferralTrigger;
use App\Models\Billing\Plan;
use App\Models\Referrals\ReferralBlockedDomain;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralRewardRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cached reads of the admin-editable referral configuration. Every cache
 * key embeds a version number, and `flush()`: called by the models' own
 * `saved`/`deleted` hooks: bumps it, so an admin's change is visible on
 * the next request without having to enumerate the keys to forget.
 *
 * Only raw attribute arrays are cached, never models: the cache refuses to
 * unserialize objects (`cache.serializable_classes` is off), so a cached
 * model would come back as an incomplete class. Models are rebuilt from the
 * arrays with `hydrate()` on the way out.
 */
class ReferralSettings
{
    private const string VERSION_KEY = 'referrals:settings:version';

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 0) + 1);
    }

    public function enabled(): bool
    {
        return (bool) config('referrals.enabled');
    }

    /**
     * The program a code without its own program (and a signup without a
     * code's program) falls under: `null` when none is marked default or
     * the default isn't live.
     */
    public function defaultProgram(): ?ReferralProgram
    {
        /** @var array<string, mixed>|null $attributes */
        $attributes = $this->remember(
            'default-program',
            fn () => ReferralProgram::query()->where('is_default', true)->first()?->getAttributes(),
        );

        $program = $attributes === null ? null : ReferralProgram::hydrate([$attributes])->first();

        return $program?->isLive() ? $program : null;
    }

    /**
     * Live rules for `$trigger` in `$program`, in `sort_order`.
     *
     * @return Collection<int, ReferralRewardRule>
     */
    public function rulesFor(ReferralProgram $program, ReferralTrigger $trigger): Collection
    {
        /** @var list<array{rule: array<string, mixed>, plan: array<string, mixed>|null}> $rows */
        $rows = $this->remember(
            "rules:{$program->id}:{$trigger->value}",
            fn () => $program->rules()
                ->where('trigger', $trigger)
                ->where('is_active', true)
                ->with('plan')
                ->get()
                ->map(fn (ReferralRewardRule $rule): array => [
                    'rule' => $rule->getAttributes(),
                    'plan' => $rule->plan?->getAttributes(),
                ])
                ->all(),
        );

        $rules = new Collection(array_map(function (array $row): ReferralRewardRule {
            $rule = ReferralRewardRule::hydrate([$row['rule']])->first();

            return $rule->setRelation('plan', $row['plan'] === null ? null : Plan::hydrate([$row['plan']])->first());
        }, $rows));

        return $rules->filter(fn (ReferralRewardRule $rule): bool => $rule->isLive())->values();
    }

    public function isBlockedDomain(string $domain): bool
    {
        /** @var array<int, string> $domains */
        $domains = $this->remember('blocked-domains', fn () => ReferralBlockedDomain::query()->pluck('domain')->all());

        return in_array(mb_strtolower($domain), $domains, true);
    }

    private function remember(string $key, \Closure $callback): mixed
    {
        $version = (int) Cache::get(self::VERSION_KEY, 0);

        return Cache::remember(
            "referrals:settings:{$version}:{$key}",
            (int) config('referrals.cache_ttl_seconds', 300),
            $callback,
        );
    }
}
