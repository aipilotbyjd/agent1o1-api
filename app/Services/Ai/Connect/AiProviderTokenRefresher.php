<?php

namespace App\Services\Ai\Connect;

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Exceptions\AiProviderConnectException;
use App\Models\Ai\AiProviderCredential;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Keeps a one-click connection's OAuth token working: renews it with its
 * refresh token before it expires. Run ahead of time by
 * `ai-credentials:refresh-tokens`, and at call time by
 * `ByokProviderRegistrar` for a token that is about to run out anyway.
 *
 * One renewal per credential at a time: a provider that rotates refresh
 * tokens would reject the second of two simultaneous renewals. A renewal
 * the provider refuses takes the credential out of rotation (`invalid`)
 * until it is connected again; one that fails because the provider couldn't
 * be reached leaves it as it was, to be tried again. The caller decides
 * whether to tell anyone.
 */
class AiProviderTokenRefresher
{
    private const int LOCK_SECONDS = 30;

    public function __construct(private readonly AiProviderConnectors $connectors) {}

    /**
     * Renews the credential in place. True when it now holds a working
     * token (renewed here, or by someone else meanwhile).
     */
    public function refresh(AiProviderCredential $credential): bool
    {
        if (! $credential->isOAuth()) {
            return true;
        }

        try {
            return Cache::lock("ai-provider-credential:{$credential->id}:refresh", self::LOCK_SECONDS)
                ->block(self::LOCK_SECONDS, fn (): bool => $this->renew($credential));
        } catch (LockTimeoutException) {
            return false;
        }
    }

    private function renew(AiProviderCredential $credential): bool
    {
        // Someone else may have renewed it while this waited for the lock.
        $credential->refresh();

        if (! $credential->tokenExpiresWithin((int) config('byok.connect.refresh_before_seconds', 900)) && $credential->isValid()) {
            return true;
        }

        $connector = $this->connectors->for($credential->execution_provider);

        try {
            $connected = $connector?->refresh($credential)
                ?? throw AiProviderConnectException::cannotRefresh($credential->execution_provider);
        } catch (AiProviderConnectException $exception) {
            // The provider couldn't be reached: keep the connection as it is
            // and let the next renewal try again.
            if ($exception->retryable) {
                return false;
            }

            $credential->forceFill([
                'validation_status' => AiProviderCredentialStatus::Invalid,
                'validation_message' => Str::limit($exception->getMessage(), 497),
                'last_validated_at' => now(),
            ])->save();

            return false;
        }

        $credential->forceFill([
            'data' => $connected->data(),
            'token_expires_at' => $connected->expiresAt,
            'key_hint' => AiProviderCredential::hintFor($connected->apiKey),
            'validation_status' => AiProviderCredentialStatus::Valid,
            'validation_message' => 'Connection renewed.',
            'last_validated_at' => now(),
        ])->save();

        return true;
    }
}
