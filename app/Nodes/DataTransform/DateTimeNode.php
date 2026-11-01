<?php

namespace App\Nodes\DataTransform;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Timezones\Timezones;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * Dates and times: the current time, a date in another format or timezone, a
 * date moved forward or back, or the gap between two dates.
 *
 * Dates come out as ISO 8601 in `result` (with `timestamp` in seconds); a
 * reformat returns the text in `result`; a gap returns the number.
 */
class DateTimeNode extends PureDataNode
{
    private const array OPERATIONS = [
        'now' => 'Current date and time',
        'format' => 'Change the format or timezone',
        'add' => 'Add time',
        'subtract' => 'Subtract time',
        'difference' => 'Time between two dates',
    ];

    private const array UNITS = [
        'seconds' => 'Seconds',
        'minutes' => 'Minutes',
        'hours' => 'Hours',
        'days' => 'Days',
        'weeks' => 'Weeks',
        'months' => 'Months',
        'years' => 'Years',
    ];

    /**
     * Seconds in the units a gap can be measured in. A month and a year
     * aren't a fixed length, so they can be added but not measured.
     *
     * @var array<string, int>
     */
    private const array UNIT_SECONDS = ['seconds' => 1, 'minutes' => 60, 'hours' => 3600, 'days' => 86400, 'weeks' => 604800];

    private const int MAX_AMOUNT = 100000;

    public function type(): string
    {
        return 'date_time';
    }

    public function name(): string
    {
        return 'Date & Time';
    }

    public function icon(): string
    {
        return 'calendar-02';
    }

    public function description(): string
    {
        return 'Gets the current time, reformats a date or moves it to another timezone, adds or subtracts time, or measures the time between two dates.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['operation'],
            'properties' => [
                'operation' => Field::select('Operation', self::OPERATIONS, default: 'now'),
                'value' => Field::text('Date', 'The date to use: 2026-03-01, 2026-03-01 14:30 or an ISO 8601 time, usually {{ ... }} from an earlier step. Leave empty to use the current time.', '2026-03-01T14:30:00Z'),
                'timezone' => Field::dynamic('Timezone', 'system.timezones', 'A date with no timezone of its own is read in this one, and results are shown in it. Leave empty for UTC.', 'UTC', allowCustom: false),
                'format' => Field::text('Format', 'Change the format: a PHP date format, e.g. Y-m-d, d/m/Y H:i or l, j F Y. Leave empty for ISO 8601.', 'Y-m-d'),
                'amount' => Field::integer('Amount', 'Add or subtract time: how many units.', 1, 0, self::MAX_AMOUNT),
                'unit' => Field::select('Unit', self::UNITS, 'Add, subtract and time between: the unit.', 'days'),
                'other' => Field::text('Other date', 'Time between two dates: the second date. The result is positive when it is later than the first.', '2026-03-15'),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'result' => ['title' => 'Result', 'description' => 'The date (ISO 8601), the reformatted text, or the time between two dates.'],
                'timestamp' => ['title' => 'Timestamp', 'description' => 'The date as seconds since 1970. Not set when measuring the time between two dates.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $operation = (string) ($config['operation'] ?? '');
        $timezone = $this->timezone($config);

        if (! array_key_exists($operation, self::OPERATIONS)) {
            throw new InvalidArgumentException("Unsupported date operation [{$operation}].");
        }

        $date = $this->parse($config['value'] ?? null, $timezone, 'value');

        return match ($operation) {
            'now' => $this->date($date),
            'format' => $this->formatted($date, $this->text($config, 'format')),
            'add' => $this->date($this->shift($date, $config, 1)),
            'subtract' => $this->date($this->shift($date, $config, -1)),
            'difference' => $this->difference($date, $this->parse($config['other'] ?? null, $timezone, 'other', required: true), $this->unit($config)),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function timezone(array $config): string
    {
        $zone = $config['timezone'] ?? null;

        if ($zone === null || $zone === '') {
            return 'UTC';
        }

        return Timezones::canonical($zone) ?? throw new InvalidArgumentException("\"{$zone}\" is not a timezone from the list.");
    }

    private function parse(mixed $value, string $timezone, string $field, bool $required = false): CarbonImmutable
    {
        if ($value === null || $value === '') {
            return $required
                ? throw new InvalidArgumentException("\"{$field}\" needs a date.")
                : CarbonImmutable::now($timezone);
        }

        if (is_numeric($value) && ! is_string($value) || (is_string($value) && preg_match('/^\d{9,11}$/', $value) === 1)) {
            return CarbonImmutable::createFromTimestampUTC((int) $value)->setTimezone($timezone);
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("\"{$field}\" must be a date.");
        }

        try {
            return CarbonImmutable::parse($value, $timezone);
        } catch (Throwable) {
            throw new InvalidArgumentException("\"{$value}\" isn't a date this step can read. Try 2026-03-01 or 2026-03-01T14:30:00Z.");
        }
    }

    /**
     * @return array{result: string, timestamp: int}
     */
    private function date(CarbonImmutable $date): array
    {
        return ['result' => $date->toIso8601String(), 'timestamp' => $date->getTimestamp()];
    }

    /**
     * @return array{result: string, timestamp: int}
     */
    private function formatted(CarbonImmutable $date, string $format): array
    {
        return ['result' => $format === '' ? $date->toIso8601String() : $date->format($format), 'timestamp' => $date->getTimestamp()];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function shift(CarbonImmutable $date, array $config, int $direction): CarbonImmutable
    {
        $amount = $config['amount'] ?? null;

        if (! is_numeric($amount) || (int) $amount != $amount || $amount < 0 || $amount > self::MAX_AMOUNT) {
            throw new InvalidArgumentException('"amount" must be a whole number from 0 to '.self::MAX_AMOUNT.'.');
        }

        $sign = $direction < 0 ? '-' : '+';

        return $date->modify("{$sign}{$amount} ".$this->unit($config));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function unit(array $config): string
    {
        $unit = (string) ($config['unit'] ?? 'days');

        if (! array_key_exists($unit, self::UNITS)) {
            throw new InvalidArgumentException("Unsupported unit [{$unit}].");
        }

        return $unit;
    }

    /**
     * @return array{result: int|float}
     */
    private function difference(CarbonImmutable $from, CarbonImmutable $to, string $unit): array
    {
        if (! isset(self::UNIT_SECONDS[$unit])) {
            throw new InvalidArgumentException('Months and years vary in length, so the time between two dates is measured in seconds, minutes, hours, days or weeks.');
        }

        $gap = ($to->getTimestamp() - $from->getTimestamp()) / self::UNIT_SECONDS[$unit];

        return ['result' => $gap == floor($gap) ? (int) $gap : round($gap, 4)];
    }
}
