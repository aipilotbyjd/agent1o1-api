<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Assistant\Channels\SmsChannel;
use App\Services\Assistant\Channels\TwilioSms;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Inbound texts from Twilio, checked against Twilio's signature. Replies
 * are sent separately when the turn ends, so Twilio gets an empty answer.
 */
class AssistantSmsWebhookController extends Controller
{
    public function __invoke(Request $request, TwilioSms $twilio, SmsChannel $sms): Response
    {
        $url = config('assistant.channels.sms.webhook_url') ?: $request->fullUrl();

        abort_unless($twilio->signatureIsValid($url, $request->post(), (string) $request->header('X-Twilio-Signature')), 403);

        $result = $sms->handle((string) $request->input('From'), (string) $request->input('Body'));
        Log::debug('Assistant SMS', ['result' => $result]);

        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200, ['Content-Type' => 'text/xml']);
    }
}
