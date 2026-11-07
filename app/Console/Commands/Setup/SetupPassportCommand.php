<?php

namespace App\Console\Commands\Setup;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

class SetupPassportCommand extends Command
{
    /**
     * The grants the first-party password client needs: password sign-in,
     * token refresh, and the social-login exchange.
     *
     * @var list<string>
     */
    public const GRANT_TYPES = ['password', 'refresh_token', 'social_exchange'];

    protected $signature = 'setup:passport
        {--no-env : Print the client ID and secret instead of writing them to .env}';

    protected $description = 'Create the Passport encryption keys and the password grant client that sign-in needs. Safe to run again.';

    public function handle(): int
    {
        if (! file_exists(Passport::keyPath('oauth-private.key'))) {
            $this->call('passport:keys');
        }

        $clientId = config('passport.password_client_id');
        $clientSecret = config('passport.password_client_secret');

        if (filled($clientId) && filled($clientSecret)) {
            $this->ensureClient((string) $clientId, (string) $clientSecret);
            $this->components->info('The password client is set up.');

            return self::SUCCESS;
        }

        $clientId = (string) Str::uuid7();
        $clientSecret = Str::random(40);

        $this->ensureClient($clientId, $clientSecret);

        $values = [
            'PASSPORT_PASSWORD_CLIENT_ID' => $clientId,
            'PASSPORT_PASSWORD_CLIENT_SECRET' => $clientSecret,
        ];

        if ($this->option('no-env') || ! $this->writeEnvironmentFile($values)) {
            $this->components->warn('Add these to your environment. The secret is not shown again.');

            foreach ($values as $key => $value) {
                $this->line("{$key}={$value}");
            }

            return self::SUCCESS;
        }

        $this->call('config:clear');
        $this->components->info('Created the password client and saved it to .env.');

        return self::SUCCESS;
    }

    /**
     * Create the client with this ID and secret, or bring an existing one up to
     * date, so a fresh database works with the ID and secret already in .env.
     */
    private function ensureClient(string $clientId, string $clientSecret): void
    {
        /** @var class-string<Client> $model */
        $model = Passport::clientModel();

        $client = $model::query()->find($clientId);

        if ($client === null) {
            $model::query()->forceCreate([
                'id' => $clientId,
                'name' => config('app.name').' Password Grant Client',
                'secret' => $clientSecret,
                'provider' => 'users',
                'redirect_uris' => [],
                'grant_types' => self::GRANT_TYPES,
                'revoked' => false,
            ]);

            return;
        }

        $client->forceFill([
            'grant_types' => array_values(array_unique([...($client->grant_types ?? []), ...self::GRANT_TYPES])),
            'revoked' => false,
        ])->save();
    }

    /**
     * Set the given keys in .env, replacing blank or existing lines.
     *
     * @param  array<string, string>  $values
     */
    private function writeEnvironmentFile(array $values): bool
    {
        $path = $this->laravel->environmentFilePath();

        if (! is_file($path) || ! is_writable($path)) {
            return false;
        }

        $contents = (string) file_get_contents($path);

        foreach ($values as $key => $value) {
            $line = "{$key}={$value}";
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents)
                ? (string) preg_replace($pattern, $line, $contents)
                : rtrim($contents, "\n")."\n{$line}\n";
        }

        return file_put_contents($path, $contents) !== false;
    }
}
