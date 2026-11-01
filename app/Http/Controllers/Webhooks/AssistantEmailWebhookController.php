<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Assistant\Channels\EmailChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound email for the assistant, posted by the mail provider. The secret
 * token in the URL proves it came from the provider.
 */
class AssistantEmailWebhookController extends Controller
{
    public function __invoke(Request $request, EmailChannel $email): JsonResponse
    {
        $secret = (string) config('assistant.channels.email.inbound_token');

        abort_if($secret === '' || ! hash_equals($secret, (string) $request->query('token')), 404);

        return response()->json(['result' => $email->handle($request->json()->all())]);
    }
}
