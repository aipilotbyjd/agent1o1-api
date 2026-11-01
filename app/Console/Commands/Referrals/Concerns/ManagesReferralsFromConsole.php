<?php

namespace App\Console\Commands\Referrals\Concerns;

use App\Models\Billing\Plan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared plumbing for the `referrals:*` admin commands:
 *
 * - `--set key=value` options, with dotted keys for nested settings
 *   (`--set fraud_checks.ip_velocity.max=3`), `null`/`true`/`false` and
 *   numbers cast, and comma-separated values for list fields
 *   (`--set conditions.billing_intervals=monthly,yearly`). `plan=<slug>`
 *   is accepted in place of `plan_id`, and plan slugs work in plan lists.
 * - Validation against the same rules the admin API uses, printing field
 *   errors instead of throwing.
 * - Finding a record by its full id or the short id the listings print:
 *   its last 8 characters. Ids are time-ordered UUIDs, so their *start* is
 *   shared by records created together; the end is the random part.
 */
trait ManagesReferralsFromConsole
{
    /**
     * Fields whose `--set` value is a comma-separated list.
     *
     * @var list<string>
     */
    private array $listFields = [
        'referrer_eligible_plan_ids',
        'conditions.plan_ids',
        'conditions.billing_intervals',
        'conditions.payment_sources',
    ];

    /**
     * @param  list<string>  $sets
     * @return array<string, mixed>
     */
    protected function parseSets(array $sets): array
    {
        $data = [];

        foreach ($sets as $set) {
            if (! str_contains($set, '=')) {
                throw ValidationException::withMessages(['--set' => "\"{$set}\" is not in key=value form."]);
            }

            [$key, $raw] = array_map('trim', explode('=', $set, 2));

            if ($key === 'plan') {
                $key = 'plan_id';
            }

            $value = in_array($key, $this->listFields, true)
                ? array_values(array_filter(array_map('trim', explode(',', $raw)), fn (string $item): bool => $item !== ''))
                : $this->cast($raw);

            if ($key === 'plan_id' && is_string($value)) {
                $value = $this->planId($value);
            }

            if (in_array($key, ['referrer_eligible_plan_ids', 'conditions.plan_ids'], true)) {
                $value = array_map(fn (string $plan): string => $this->planId($plan), $value);
            }

            Arr::set($data, $key, $value);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>|null The validated data, or null after printing the errors.
     */
    protected function validateInput(array $data, array $rules): ?array
    {
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            $this->printErrors($validator->errors()->toArray());

            return null;
        }

        return $validator->validated();
    }

    /**
     * @param  array<string, list<string>|string>  $errors
     */
    protected function printErrors(array $errors): void
    {
        foreach ($errors as $field => $messages) {
            foreach ((array) $messages as $message) {
                $this->error("{$field}: {$message}");
            }
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel|null
     */
    protected function findByShortId(Builder $query, string $key, string $label): ?Model
    {
        $exact = (clone $query)->whereKey($key)->first();

        if ($exact !== null) {
            return $exact;
        }

        $matches = (clone $query)->where($query->getModel()->getKeyName(), 'like', '%'.$key)->limit(2)->get();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        $this->error($matches->isEmpty() ? "No {$label} matches \"{$key}\"." : "\"{$key}\" matches more than one {$label}; use more of the id.");

        return null;
    }

    protected function shortId(?string $id): string
    {
        return $id === null ? '-' : Str::substr($id, -8);
    }

    protected function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    private function cast(string $raw): mixed
    {
        return match (true) {
            $raw === '' || strtolower($raw) === 'null' => null,
            strtolower($raw) === 'true' => true,
            strtolower($raw) === 'false' => false,
            is_numeric($raw) => str_contains($raw, '.') ? (float) $raw : (int) $raw,
            default => $raw,
        };
    }

    private function planId(string $planOrSlug): string
    {
        return Plan::query()->where('slug', $planOrSlug)->value('id') ?? $planOrSlug;
    }
}
