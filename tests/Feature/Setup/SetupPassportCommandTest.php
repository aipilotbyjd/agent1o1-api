<?php

use App\Console\Commands\Setup\SetupPassportCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->originalKeyPath = Passport::$keyPath;
    $this->directory = storage_path('framework/testing/setup-passport-'.Str::random(8));

    File::ensureDirectoryExists($this->directory);
    File::put($this->directory.'/oauth-private.key', 'existing');
    Passport::loadKeysFrom($this->directory);

    $this->app->useEnvironmentPath($this->directory);
});

afterEach(function () {
    Passport::$keyPath = $this->originalKeyPath;
    File::deleteDirectory($this->directory);
});

it('creates the password client with every grant and writes it to .env', function () {
    config(['passport.password_client_id' => null, 'passport.password_client_secret' => null]);
    File::put($this->directory.'/.env', "APP_NAME=Agent1o1\nPASSPORT_PASSWORD_CLIENT_ID=\nPASSPORT_PASSWORD_CLIENT_SECRET=\n");

    $this->artisan('setup:passport')->assertSuccessful();

    $env = File::get($this->directory.'/.env');
    preg_match('/^PASSPORT_PASSWORD_CLIENT_ID=(.+)$/m', $env, $id);
    preg_match('/^PASSPORT_PASSWORD_CLIENT_SECRET=(.+)$/m', $env, $secret);

    $client = Client::query()->findOrFail($id[1]);

    expect($env)->toStartWith("APP_NAME=Agent1o1\n")
        ->and($client->grant_types)->toBe(SetupPassportCommand::GRANT_TYPES)
        ->and($client->revoked)->toBeFalse()
        ->and(password_verify($secret[1], $client->secret))->toBeTrue();
});

it('prints the client instead of writing it with --no-env', function () {
    config(['passport.password_client_id' => null, 'passport.password_client_secret' => null]);
    File::put($this->directory.'/.env', "PASSPORT_PASSWORD_CLIENT_ID=\n");

    $this->artisan('setup:passport --no-env')
        ->expectsOutputToContain('PASSPORT_PASSWORD_CLIENT_SECRET=')
        ->assertSuccessful();

    expect(File::get($this->directory.'/.env'))->toBe("PASSPORT_PASSWORD_CLIENT_ID=\n");
});

it('recreates the client from .env after the database was reset', function () {
    $id = (string) Str::uuid7();
    config(['passport.password_client_id' => $id, 'passport.password_client_secret' => 'kept-secret']);

    $this->artisan('setup:passport')->assertSuccessful();

    $client = Client::query()->findOrFail($id);

    expect($client->grant_types)->toBe(SetupPassportCommand::GRANT_TYPES)
        ->and(password_verify('kept-secret', $client->secret))->toBeTrue();
});

it('adds the missing grants to an existing client and keeps its secret', function () {
    $client = Client::query()->findOrFail(config('passport.password_client_id'));
    $client->forceFill(['grant_types' => ['password', 'refresh_token']])->save();
    $hashedSecret = $client->fresh()->secret;

    $this->artisan('setup:passport')->assertSuccessful();

    expect($client->fresh()->grant_types)->toBe(SetupPassportCommand::GRANT_TYPES)
        ->and($client->fresh()->secret)->toBe($hashedSecret)
        ->and(Client::query()->count())->toBe(1);
});
