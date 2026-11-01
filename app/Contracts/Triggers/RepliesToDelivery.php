<?php

namespace App\Contracts\Triggers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A provider ingress that must answer an event with more than "received": Discord
 * wants an interaction answered (with "thinking…") within three seconds, in a
 * body that says how. The reply is built from the request alone and sent after
 * the events are stored, so it never waits on a flow.
 */
interface RepliesToDelivery
{
    /**
     * The body to answer the delivery with: an array is sent as JSON.
     *
     * @return array<string, mixed>|Response
     */
    public function reply(Request $request): array|Response;
}
