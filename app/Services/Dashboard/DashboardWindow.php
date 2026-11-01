<?php

namespace App\Services\Dashboard;

use Carbon\CarbonImmutable;

/**
 * The rolling time window every dashboard endpoint reports over, and the one
 * place that decides what "the last N days" means, so the `window` block,
 * the `WHERE created_at >=` bound, and a series' buckets can never disagree
 * about where the window starts.
 *
 * Days are whole UTC calendar days ending at the current moment (the app runs
 * on UTC, per `config/app.php`), not rolling 24-hour slices: `days = 1` is
 * "since midnight today", `days = 30` is "since midnight 29 days ago". A
 * partial day at the leading edge is the point: a dashboard opened at 09:00
 * should show this morning's runs.
 */
final readonly class DashboardWindow
{
    private function __construct(
        public int $days,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public static function ofDays(int $days): self
    {
        $to = CarbonImmutable::now();

        return new self($days, $to->startOfDay()->subDays($days - 1), $to);
    }

    /**
     * Every `Y-m-d` bucket in the window, oldest first. Series are zero-filled
     * against this so a chart always gets one point per day, including days
     * with no activity, otherwise a quiet Sunday silently closes the gap
     * between Saturday and Monday.
     *
     * @return array<int, string>
     */
    public function dateKeys(): array
    {
        $keys = [];

        for ($offset = 0; $offset < $this->days; $offset++) {
            $keys[] = $this->from->addDays($offset)->format('Y-m-d');
        }

        return $keys;
    }

    /**
     * @return array{days: int, starts_at: string, ends_at: string}
     */
    public function toArray(): array
    {
        return [
            'days' => $this->days,
            'starts_at' => $this->from->toIso8601String(),
            'ends_at' => $this->to->toIso8601String(),
        ];
    }
}
