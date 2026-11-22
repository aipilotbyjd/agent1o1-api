<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function passwordClientWithGrants(array $grantTypes): string
{
    $id = (string) Str::uuid();

    DB::connection(config('passport.connection'))->table('oauth_clients')->insert([
        'id' => $id,
        'name' => 'Agent1o1 Password Grant',
        'redirect_uris' => '[]',
        'grant_types' => json_encode($grantTypes),
        'revoked' => false,
    ]);

    config(['passport.password_client_id' => $id]);

    return $id;
}

function grantsOf(string $clientId): array
{
    return json_decode(
        DB::connection(config('passport.connection'))->table('oauth_clients')->where('id', $clientId)->value('grant_types'),
        true,
    );
}

it('adds the social_exchange grant and keeps the client\'s other grants', function () {
    $id = passwordClientWithGrants(['password', 'refresh_token']);

    $this->artisan('passport:allow-social-exchange')->assertSuccessful();

    expect(grantsOf($id))->toBe(['password', 'refresh_token', 'social_exchange']);
});

it('leaves the grants alone when it is run again', function () {
    $id = passwordClientWithGrants(['password', 'refresh_token']);

    $this->artisan('passport:allow-social-exchange')->assertSuccessful();
    $this->artisan('passport:allow-social-exchange')->assertSuccessful();

    expect(grantsOf($id))->toBe(['password', 'refresh_token', 'social_exchange']);
});

it('succeeds without touching anything while no client id is configured', function () {
    $id = passwordClientWithGrants(['password']);
    config(['passport.password_client_id' => null]);

    $this->artisan('passport:allow-social-exchange')->assertSuccessful();

    expect(grantsOf($id))->toBe(['password']);
});

it('fails when the configured client id matches no client', function () {
    config(['passport.password_client_id' => (string) Str::uuid()]);

    $this->artisan('passport:allow-social-exchange')->assertFailed();
});
