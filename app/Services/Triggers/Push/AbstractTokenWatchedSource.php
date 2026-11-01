<?php

namespace App\Services\Triggers\Push;

use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Throwable;

/**
 * A watched source for a provider that sends unsigned webhooks to a URL we give
 * it: the URL carries a random token, found again by its hash (see
 * AbstractTokenIngress). The watch is saved before the provider hears of the URL,
 * because some providers call it at once to check it, and the ingress finds the
 * token on the watch.
 */
abstract class AbstractTokenWatchedSource extends AbstractWatchedSource
{
    /**
     * Events are filed under the hash of the token.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        return $watch?->meta['token_hash'] ?? null;
    }

    /**
     * Give the watch a fresh token, save it, and let `$register` tell the provider
     * the URL. If that fails the watch is put back as it was.
     *
     * @param  callable(string): void  $register  receives the callback URL
     * @param  array<string, mixed>  $meta  more to record on the watch
     *
     * @throws Throwable what `$register` throws
     */
    protected function registerUrl(ProviderWatch $watch, callable $register, array $meta = []): void
    {
        $token = $this->newSecret();
        $existed = $watch->exists;
        $before = ['secret' => $watch->secret, 'meta' => $watch->meta, 'external_id' => $watch->external_id];

        $watch->forceFill(['secret' => $token, 'meta' => [...(array) $watch->meta, ...$meta, 'token_hash' => hash('sha256', $token)]])->save();

        try {
            $register($this->callbackUrl().'?token='.$token);
        } catch (Throwable $exception) {
            $existed ? $watch->forceFill($before)->save() : $watch->delete();

            throw $exception;
        }
    }
}
