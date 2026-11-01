<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use App\Models\Triggers\ProviderWatch;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The door for a provider whose webhooks are not signed. Each watch registered a
 * URL with a random token (`?token=`) that is kept hashed on the watch; the token
 * is the whole check. What a delivery says is never trusted: it only makes the
 * trigger's own poll look now, so a forged or replayed request can at worst cost
 * one read.
 *
 * Some of these providers confirm a new URL with a GET or an empty POST before
 * they accept it; both pass when the token is right and carry no events.
 */
abstract class AbstractTokenIngress implements ProviderIngress
{
    public function verify(Request $request): bool
    {
        $token = (string) $request->query('token');

        return $token !== '' && ProviderWatch::query()->where('provider', $this->provider())->where('meta->token_hash', hash('sha256', $token))->exists();
    }

    public function handshake(Request $request): array|Response|null
    {
        return null;
    }

    /**
     * What the watch is filed under: the hash of the URL's token.
     */
    protected function routingKey(Request $request): string
    {
        return hash('sha256', (string) $request->query('token'));
    }
}
