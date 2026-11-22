<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // The array store only serializes (and so only applies the allow-list) when asked to.
    $this->store = Cache::build(['driver' => 'array', 'serialize' => true]);
});

test('a pulse card reads back the query result it cached', function () {
    $result = [
        collect([(object) [
            'server' => 'web',
            'storage' => collect(['/' => 42]),
            'updated_at' => CarbonImmutable::parse('2026-11-22 10:00:00', 'UTC'),
        ]]),
        1.25,
        '2026-11-22 10:00:00',
    ];

    $this->store->put('laravel:pulse:card', $result, 60);

    [$rows, $duration, $runAt] = $this->store->get('laravel:pulse:card');

    expect($rows)->toBeInstanceOf(Collection::class)
        ->and($rows->first())->toBeInstanceOf(stdClass::class)
        ->and($rows->first()->storage)->toBeInstanceOf(Collection::class)
        ->and($rows->first()->updated_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($rows->first()->updated_at->toDateTimeString())->toBe('2026-11-22 10:00:00')
        ->and($duration)->toBe(1.25)
        ->and($runAt)->toBe('2026-11-22 10:00:00');
});

test('other classes are still refused when they come back from the cache', function () {
    $this->store->put('gadget', new ArrayObject(['a' => 1]), 60);

    expect($this->store->get('gadget'))->not->toBeInstanceOf(ArrayObject::class);
});
