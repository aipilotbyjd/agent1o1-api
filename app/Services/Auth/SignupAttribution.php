<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Where a new account came from: the campaign tags on the link they arrived
 * by, the page they landed on, and the template it pointed at, if any. The
 * browser keeps these from the first visit and sends them once: with the
 * signup form, or straight after a Google or GitHub signup. Only the first
 * set is kept, and only for an account made in the last day, so signing in
 * again from another link never rewrites or invents it.
 */
final class SignupAttribution
{
    /**
     * @var array<int, string>
     */
    public const array FIELDS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'referrer',
        'landing_path',
        'template',
    ];

    private const int MAX_LENGTH = 255;

    /**
     * How old an account may be and still have its signup attributed: a
     * Google or GitHub sign-in is attributed straight after it, so anything
     * older is someone signing in again.
     */
    private const int MAX_ACCOUNT_AGE_HOURS = 24;

    /**
     * Validation for an `attribution` object of these fields.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(string $prefix = 'attribution'): array
    {
        $rules = [$prefix => ['nullable', 'array']];

        foreach (self::FIELDS as $field) {
            $rules["{$prefix}.{$field}"] = ['nullable', 'string', 'max:2048'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $attribution
     */
    public function record(User $user, array $attribution): void
    {
        $clean = collect($attribution)
            ->only(self::FIELDS)
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => Str::limit(trim($value), self::MAX_LENGTH, ''))
            ->all();

        if ($clean === [] || $user->created_at?->lt(now()->subHours(self::MAX_ACCOUNT_AGE_HOURS))) {
            return;
        }

        User::query()
            ->whereKey($user->getKey())
            ->whereNull('signup_attribution')
            ->update(['signup_attribution' => json_encode($clean, JSON_THROW_ON_ERROR)]);
    }
}
