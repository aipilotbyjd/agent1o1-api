<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Social sign-in now issues tokens through the `social_exchange` grant instead
 * of rotating the user's password and running the password grant. Passport
 * refuses a grant the client does not list, so the client that already exists
 * in every environment has to learn about it, otherwise the first social
 * sign-in after deploying fails with "unsupported grant type".
 */
return new class extends Migration
{
    private const GRANT = 'social_exchange';

    public function up(): void
    {
        $this->mapGrantTypes(fn (array $grantTypes) => in_array(self::GRANT, $grantTypes, strict: true)
            ? $grantTypes
            : [...$grantTypes, self::GRANT]);
    }

    public function down(): void
    {
        $this->mapGrantTypes(fn (array $grantTypes) => array_values(
            array_filter($grantTypes, fn (string $grant): bool => $grant !== self::GRANT),
        ));
    }

    /**
     * @param  callable(array<int, string>): array<int, string>  $callback
     */
    private function mapGrantTypes(callable $callback): void
    {
        $clientId = config('passport.password_client_id');

        if (blank($clientId)) {
            return;
        }

        $table = DB::connection(config('passport.connection'))->table('oauth_clients');

        $client = $table->where('id', $clientId)->first();

        if ($client === null || $client->grant_types === null) {
            return;
        }

        $grantTypes = json_decode((string) $client->grant_types, true);

        if (! is_array($grantTypes)) {
            return;
        }

        $table->where('id', $clientId)->update([
            'grant_types' => json_encode($callback($grantTypes)),
        ]);
    }
};
