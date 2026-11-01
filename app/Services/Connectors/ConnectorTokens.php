<?php

namespace App\Services\Connectors;

use App\Exceptions\ConnectorException;
use App\Models\Connectors\ConnectorCredential;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The one place an app's access token is read: it hands back a token that
 * is valid now, refreshing an OAuth connection that is about to expire (or
 * already has). Refreshes take a per-connection lock, because providers
 * that rotate refresh tokens (Microsoft) invalidate the old one: two
 * workers refreshing at once would leave the connection broken.
 */
class ConnectorTokens
{
    /**
     * Refresh this long before expiry, so a token never runs out mid-request.
     */
    private const int EXPIRY_MARGIN_SECONDS = 120;

    private const int LOCK_SECONDS = 30;

    private const int LOCK_WAIT_SECONDS = 20;

    public function __construct(private readonly OAuthConnectorFlowService $flow) {}

    /**
     * `$markUsed` is off only for a connection check, which shouldn't read
     * as the account having been used.
     *
     * @throws ConnectorException when the connection has expired and can't be renewed
     */
    public function accessToken(ConnectorCredential $credential, bool $markUsed = true): string
    {
        if ($this->expiresSoon($credential)) {
            $credential = $this->refresh($credential);
        }

        $token = $credential->data['access_token'] ?? $credential->data['api_key'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new ConnectorException("Connector credential [{$credential->id}] has no usable access token. Reconnect it in Apps.");
        }

        if ($markUsed) {
            $credential->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $token;
    }

    /**
     * Renews the access token now. Under the lock the credential is read
     * again: if another worker refreshed it meanwhile, that token is used.
     * A refresh the provider rejects ends the connection: it's marked
     * expired so it isn't retried, and the member is asked to reconnect.
     *
     * @throws ConnectorException
     */
    public function refresh(ConnectorCredential $credential, bool $force = false): ConnectorCredential
    {
        if (! $credential->canRefresh()) {
            if ($credential->isExpired()) {
                throw ConnectorException::needsReconnect($credential);
            }

            return $credential;
        }

        return Cache::lock("connector-credential-refresh:{$credential->id}", self::LOCK_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, function () use ($credential, $force): ConnectorCredential {
                $current = $credential->fresh(['connector']) ?? $credential;

                if (! $force && ! $this->expiresSoon($current)) {
                    return $current;
                }

                try {
                    return $this->flow->refresh($current);
                } catch (ConnectorException $e) {
                    // The provider answered and said no: this refresh token is done.
                    report($e);
                    $this->markRejected($current);
                } catch (Throwable $e) {
                    // The provider couldn't be reached; the next use tries again.
                    report($e);
                }

                if ($current->isExpired()) {
                    throw ConnectorException::needsReconnect($current);
                }

                return $current;
            });
    }

    /**
     * Stops further refresh attempts; the member reconnects the app.
     */
    public function markRejected(ConnectorCredential $credential): void
    {
        $credential->forceFill(['data' => [...$credential->data, 'refresh_rejected_at' => now()->toIso8601String()]])->save();
    }

    private function expiresSoon(ConnectorCredential $credential): bool
    {
        return $credential->expires_at !== null
            && $credential->expires_at->lte(now()->addSeconds(self::EXPIRY_MARGIN_SECONDS));
    }
}
