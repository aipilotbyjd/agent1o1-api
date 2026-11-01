<?php

namespace App\Services\Ai;

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Models\Ai\AiProviderCredential;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Asks a provider whether an API key works, with one cheap authenticated
 * GET (`config('byok.providers.*.check')`): no tokens spent. Three
 * outcomes, because "the provider said no" and "we couldn't reach the
 * provider" mean different things: a rejected key is never used, while a
 * provider outage leaves what we already knew about the key alone.
 */
class AiProviderKeyChecker
{
    private const int TIMEOUT_SECONDS = 10;

    /**
     * @return array{ok: bool, rejected: bool, message: string}
     */
    public function check(string $provider, string $apiKey): array
    {
        $label = $this->label($provider);
        $check = config("byok.providers.{$provider}.check");

        if (! is_array($check)) {
            return ['ok' => false, 'rejected' => true, 'message' => "{$label} keys aren't supported."];
        }

        try {
            $response = $this->request($check['auth'], $apiKey)->get($check['url'], $check['auth'] === 'query' ? ['key' => $apiKey] : []);
        } catch (ConnectionException) {
            return ['ok' => false, 'rejected' => false, 'message' => "Couldn't reach {$label}. We'll check the key again shortly."];
        }

        // Gemini answers a bad key with 400 API_KEY_INVALID rather than 401.
        if (in_array($response->status(), [401, 403], true) || ($check['auth'] === 'query' && $response->status() === 400)) {
            return ['ok' => false, 'rejected' => true, 'message' => "{$label} rejected this key. Check it was copied in full and hasn't been revoked."];
        }

        // Rate-limited or out of quota still means the key itself is genuine.
        if ($response->successful() || $response->status() === 429) {
            return ['ok' => true, 'rejected' => false, 'message' => $response->status() === 429
                ? "Key works, but {$label} is rate-limiting it right now."
                : "Connected to {$label}."];
        }

        return ['ok' => false, 'rejected' => false, 'message' => "{$label} didn't respond as expected (HTTP {$response->status()}). We'll check the key again shortly."];
    }

    /**
     * Checks a stored key and records the result on it. A key the provider
     * couldn't be reached about keeps its previous status.
     *
     * @return array{ok: bool, rejected: bool, message: string}
     */
    public function validate(AiProviderCredential $credential): array
    {
        $result = $this->check($credential->execution_provider, $credential->apiKey());

        $status = match (true) {
            $result['ok'] => AiProviderCredentialStatus::Valid,
            $result['rejected'] => AiProviderCredentialStatus::Invalid,
            default => $credential->validation_status,
        };

        $credential->forceFill([
            'validation_status' => $status,
            'validation_message' => Str::limit($result['message'], 497),
            'last_validated_at' => now(),
        ])->save();

        return $result;
    }

    public function label(string $provider): string
    {
        return (string) config("byok.providers.{$provider}.label", Str::headline($provider));
    }

    private function request(string $auth, string $apiKey): PendingRequest
    {
        $request = Http::acceptJson()->timeout(self::TIMEOUT_SECONDS);

        return match ($auth) {
            'anthropic' => $request->withHeaders(['x-api-key' => $apiKey, 'anthropic-version' => '2023-06-01']),
            'query' => $request,
            default => $request->withToken($apiKey),
        };
    }
}
