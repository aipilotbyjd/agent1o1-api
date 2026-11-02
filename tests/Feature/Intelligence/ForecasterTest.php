<?php

use App\Services\Intelligence\Forecaster;

it('carries a flat series forward with a tight band', function () {
    $forecast = app(Forecaster::class)->forecast(array_fill(0, 28, 10.0), 7, 1);

    expect($forecast['method'])->toBe('weekly_seasonal_trend')
        ->and(array_column($forecast['points'], 'value'))->each->toEqualWithDelta(10.0, 0.0001)
        ->and($forecast['points'][0]['upper'] - $forecast['points'][0]['lower'])->toBeLessThan(0.0001);
});

it('follows a steady trend', function () {
    $history = array_map(fn (int $day): float => 10.0 + $day, range(0, 27));

    $points = app(Forecaster::class)->forecast($history, 3, 1)['points'];

    expect($points[0]['value'])->toEqualWithDelta(38.0, 0.01)
        ->and($points[2]['value'])->toEqualWithDelta(40.0, 0.01);
});

it('keeps the weekly pattern: quiet weekends stay quiet', function () {
    $week = [10.0, 10.0, 10.0, 10.0, 10.0, 2.0, 2.0];
    $history = array_merge($week, $week, $week, $week);

    $points = app(Forecaster::class)->forecast($history, 7, 1)['points'];

    expect($points[0]['value'])->toBeGreaterThan($points[5]['value'] * 3)
        ->and($points[5]['value'])->toEqualWithDelta(2.0, 0.5);
});

it('falls back to the average with too little history, and never forecasts below zero', function () {
    $forecast = app(Forecaster::class)->forecast([4.0, 6.0], 2, 3);

    expect($forecast['method'])->toBe('average')
        ->and($forecast['points'][0]['value'])->toBe(5.0)
        ->and($forecast['points'][0]['lower'])->toBeGreaterThanOrEqual(0.0);
});

it('measures accuracy as the mean absolute percentage error, ignoring zero days', function () {
    expect(app(Forecaster::class)->mape([
        ['value' => 110.0, 'actual' => 100.0],
        ['value' => 90.0, 'actual' => 100.0],
        ['value' => 5.0, 'actual' => 0.0],
    ]))->toBe(10.0);
});
