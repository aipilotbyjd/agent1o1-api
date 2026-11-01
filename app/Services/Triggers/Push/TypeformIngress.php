<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use App\Models\Triggers\ProviderWatch;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Typeform's webhook. Every delivery carries `Typeform-Signature:
 * sha256=<base64 HMAC-SHA256 of the raw body>`, made with the secret of the
 * webhook that sent it. The body names the form, and the form's watches hold
 * the secrets: the request is genuine if one of them signs it.
 */
class TypeformIngress implements ProviderIngress
{
    public function provider(): string
    {
        return 'typeform';
    }

    public function verify(Request $request): bool
    {
        $form = (string) $request->json('form_response.form_id');
        $signature = (string) $request->header('Typeform-Signature');

        if ($form === '' || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        foreach (ProviderWatch::query()->where('provider', 'typeform')->where('external_id', $form)->get() as $watch) {
            $secret = $watch->secret;

            if (is_string($secret) && hash_equals('sha256='.base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true)), $signature)) {
                return true;
            }
        }

        return false;
    }

    public function handshake(Request $request): array|Response|null
    {
        return null;
    }

    public function events(Request $request): array
    {
        $form = (string) $request->json('form_response.form_id');

        if ($form === '') {
            return [];
        }

        return [[
            'event_id' => (string) ($request->json('event_id') ?: $request->json('form_response.token')),
            'event_type' => (string) $request->json('event_type', 'form_response'),
            'routing_key' => $form,
            'payload' => ['form_id' => $form, 'token' => $request->json('form_response.token')],
        ]];
    }
}
