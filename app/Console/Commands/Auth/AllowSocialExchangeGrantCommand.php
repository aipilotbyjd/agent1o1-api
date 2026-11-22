<?php

namespace App\Console\Commands\Auth;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AllowSocialExchangeGrantCommand extends Command
{
    private const GRANT = 'social_exchange';

    protected $signature = 'passport:allow-social-exchange';

    protected $description = 'Let the password client use the social_exchange grant that Google and GitHub sign-in finish with. Safe to run again.';

    public function handle(): int
    {
        $clientId = config('passport.password_client_id');

        if (blank($clientId)) {
            $this->components->warn('PASSPORT_PASSWORD_CLIENT_ID is not set, so there is no client to update.');

            return self::SUCCESS;
        }

        $clients = DB::connection(config('passport.connection'))->table('oauth_clients')->where('id', $clientId);
        $client = $clients->first();

        if ($client === null) {
            $this->components->error('No Passport client has the id in PASSPORT_PASSWORD_CLIENT_ID.');

            return self::FAILURE;
        }

        $grantTypes = json_decode((string) $client->grant_types, true);

        if (! is_array($grantTypes)) {
            $this->components->error('The client\'s grant types could not be read.');

            return self::FAILURE;
        }

        if (in_array(self::GRANT, $grantTypes, strict: true)) {
            $this->components->info('The client already allows the '.self::GRANT.' grant.');

            return self::SUCCESS;
        }

        $clients->update(['grant_types' => json_encode([...$grantTypes, self::GRANT])]);

        $this->components->info('The client now allows the '.self::GRANT.' grant.');

        return self::SUCCESS;
    }
}
