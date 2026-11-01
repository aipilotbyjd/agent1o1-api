<?php

namespace App\Casts;

use App\Services\Timezones\Timezones;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A timezone column: written under its current IANA name, and read back as
 * one, falling back to UTC for anything `Timezones` doesn't know. Cast with
 * `Timezone::class.':nullable'` to keep an empty value empty instead.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class Timezone implements CastsAttributes
{
    private bool $nullable;

    public function __construct(?string $mode = null)
    {
        $this->nullable = $mode === 'nullable';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->nullable ? Timezones::canonical($value) : Timezones::resolve($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->nullable ? Timezones::canonical($value) : Timezones::resolve($value);
    }
}
