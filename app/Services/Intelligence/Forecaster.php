<?php

namespace App\Services\Intelligence;

/**
 * A small, explainable forecast for a daily series: a linear trend over the
 * last four weeks on top of day-of-week factors, with a band from how far
 * the history strayed from that fit (about 90% of days fall inside it).
 *
 * Deliberately simple. Every number it gives can be explained from the
 * inputs it returns, and `ForecastService` records its accuracy against what
 * actually happened, so a better model can replace it once there is enough
 * history to show that it would be better.
 */
class Forecaster
{
    private const float BAND_Z = 1.645;

    private const int TREND_WINDOW = 28;

    /**
     * @param  list<float>  $history  one value per day, oldest first, ending yesterday or today
     * @param  int  $firstDayOfWeek  ISO day of week (1 = Monday) of the first history value
     * @return array{method: string, points: list<array{offset: int, value: float, lower: float, upper: float}>, inputs: array<string, mixed>}
     */
    public function forecast(array $history, int $horizon, int $firstDayOfWeek): array
    {
        $count = count($history);

        if ($count < 7) {
            $mean = $count > 0 ? array_sum($history) / $count : 0.0;
            $sigma = $this->stddev($history, array_fill(0, $count, $mean));

            return [
                'method' => 'average',
                'points' => $this->points($horizon, fn (): float => $mean, fn (): float => $sigma),
                'inputs' => ['days' => $count, 'mean' => round($mean, 6)],
            ];
        }

        $factors = $this->weekdayFactors($history, $firstDayOfWeek);
        $dayOfWeek = fn (int $index): int => (($firstDayOfWeek - 1 + $index) % 7) + 1;
        $deseasoned = [];

        foreach ($history as $index => $value) {
            $deseasoned[] = $value / $factors[$dayOfWeek($index)];
        }

        $window = array_slice($deseasoned, -self::TREND_WINDOW);
        $offset = $count - count($window);
        [$intercept, $slope] = $this->regression($window);

        $fitted = [];

        foreach ($history as $index => $value) {
            $fitted[] = max(0.0, ($intercept + $slope * ($index - $offset)) * $factors[$dayOfWeek($index)]);
        }

        $sigma = $this->stddev(array_slice($history, $offset), array_slice($fitted, $offset));

        return [
            'method' => 'weekly_seasonal_trend',
            'points' => $this->points(
                $horizon,
                fn (int $step): float => max(0.0, ($intercept + $slope * ($count - $offset + $step - 1)) * $factors[$dayOfWeek($count + $step - 1)]),
                fn (int $step): float => $sigma * $factors[$dayOfWeek($count + $step - 1)],
            ),
            'inputs' => [
                'days' => $count,
                'trend_per_day' => round($slope, 6),
                'level' => round($intercept + $slope * (count($window) - 1), 6),
                'weekday_factors' => array_map(fn (float $factor): float => round($factor, 4), $factors),
                'residual_stddev' => round($sigma, 6),
            ],
        ];
    }

    /**
     * Mean absolute percentage error of predictions against actuals,
     * ignoring days whose actual was zero.
     *
     * @param  list<array{value: float, actual: float}>  $pairs
     */
    public function mape(array $pairs): ?float
    {
        $errors = [];

        foreach ($pairs as $pair) {
            if ($pair['actual'] > 0) {
                $errors[] = abs($pair['value'] - $pair['actual']) / $pair['actual'];
            }
        }

        return $errors === [] ? null : round(array_sum($errors) / count($errors) * 100, 1);
    }

    /**
     * @param  callable(int): float  $value
     * @param  callable(int): float  $spread
     * @return list<array{offset: int, value: float, lower: float, upper: float}>
     */
    private function points(int $horizon, callable $value, callable $spread): array
    {
        $points = [];

        for ($step = 1; $step <= $horizon; $step++) {
            $center = $value($step);
            $band = self::BAND_Z * $spread($step);

            $points[] = [
                'offset' => $step,
                'value' => round($center, 6),
                'lower' => round(max(0.0, $center - $band), 6),
                'upper' => round($center + $band, 6),
            ];
        }

        return $points;
    }

    /**
     * How each day of the week compares with the trend on that day. Worked
     * out against a straight line through the history rather than its plain
     * average, so a steady rise is not mistaken for a weekly pattern.
     *
     * @param  list<float>  $history
     * @return array<int, float> ISO day of week => factor, averaging 1
     */
    private function weekdayFactors(array $history, int $firstDayOfWeek): array
    {
        [$intercept, $slope] = $this->regression($history);
        $sums = array_fill(1, 7, 0.0);
        $counts = array_fill(1, 7, 0);

        foreach ($history as $index => $value) {
            $trend = $intercept + $slope * $index;

            if ($trend > 0) {
                $day = (($firstDayOfWeek - 1 + $index) % 7) + 1;
                $sums[$day] += $value / $trend;
                $counts[$day]++;
            }
        }

        $raw = [];

        foreach (range(1, 7) as $day) {
            $raw[$day] = $counts[$day] > 0 ? $sums[$day] / $counts[$day] : 1.0;
        }

        $average = array_sum($raw) / 7;
        $factors = [];

        foreach ($raw as $day => $ratio) {
            $factors[$day] = $average > 0 ? max(0.05, $ratio / $average) : 1.0;
        }

        return $factors;
    }

    /**
     * Least-squares line through the values against their index.
     *
     * @param  list<float>  $values
     * @return array{0: float, 1: float} intercept, slope
     */
    private function regression(array $values): array
    {
        $n = count($values);
        $meanX = ($n - 1) / 2;
        $meanY = array_sum($values) / $n;
        $covariance = 0.0;
        $variance = 0.0;

        foreach ($values as $x => $y) {
            $covariance += ($x - $meanX) * ($y - $meanY);
            $variance += ($x - $meanX) ** 2;
        }

        $slope = $variance > 0 ? $covariance / $variance : 0.0;

        return [$meanY - $slope * $meanX, $slope];
    }

    /**
     * @param  list<float>  $actual
     * @param  list<float>  $fitted
     */
    private function stddev(array $actual, array $fitted): float
    {
        $count = count($actual);

        if ($count < 2) {
            return 0.0;
        }

        $sum = 0.0;

        foreach ($actual as $index => $value) {
            $sum += ($value - $fitted[$index]) ** 2;
        }

        return sqrt($sum / ($count - 1));
    }
}
