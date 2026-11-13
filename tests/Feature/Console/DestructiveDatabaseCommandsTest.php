<?php

use App\Providers\AppServiceProvider;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

afterEach(function () {
    DB::prohibitDestructiveCommands(false);
});

function bootProviderIn(string $environment): void
{
    app()->detectEnvironment(fn () => $environment);

    (new ReflectionMethod(AppServiceProvider::class, 'configureDestructiveDatabaseCommands'))
        ->invoke(new AppServiceProvider(app()));
}

it('refuses to wipe the database in production', function (string $command) {
    bootProviderIn('production');

    expect(Artisan::call($command, ['--force' => true]))->not->toBe(0);
})->with(['db:wipe', 'migrate:fresh', 'migrate:reset']);

it('leaves destructive commands available outside production', function () {
    bootProviderIn('testing');

    expect((new ReflectionProperty(FreshCommand::class, 'prohibitedFromRunning'))->getValue())->toBeFalse();
});
