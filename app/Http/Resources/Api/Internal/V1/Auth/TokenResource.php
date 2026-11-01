<?php

namespace App\Http\Resources\Api\Internal\V1\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Passport\Token;

/**
 * @mixin Token
 */
class TokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'scopes' => $this->scopes,
            'revoked' => $this->revoked,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'last_used_at' => $this->last_used_at,
            // Lets the UI label the session the caller is on, and warn before
            // revoking the one they are holding.
            'is_current' => $this->id === $this->currentTokenId($request),
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Read from the `oauth_access_token_id` claim on the guard's `AccessToken`
     * value object rather than `->id`, which would lazily load the whole row.
     * A cookie-authenticated request carries a `TransientToken` with no row
     * behind it, so nothing in the list is "current" for one.
     */
    private function currentTokenId(Request $request): ?string
    {
        $token = $request->user('api')?->token();

        if ($token === null || $token->transient()) {
            return null;
        }

        $tokenId = $token->oauth_access_token_id;

        return is_string($tokenId) && $tokenId !== '' ? $tokenId : null;
    }
}
