<?php

namespace App\Enums\Triggers;

/**
 * How a webhook request proves it came from you, chosen on the Webhook trigger
 * node (`config.authentication`).
 *
 * The secret it's checked against is the trigger's encrypted `signing_secret`,
 * set on the published trigger and never stored in the workflow.
 *
 * @see WebhookSignatureVerifier
 */
enum WebhookAuthentication: string
{
    /**
     * Anyone with the URL can start a run.
     */
    case None = 'none';

    /**
     * The sender puts the secret in a header.
     */
    case HeaderToken = 'header_token';

    /**
     * The sender signs the raw body: `X-Signature-256: sha256=<HMAC-SHA256>`.
     */
    case Signature = 'signature';

    /**
     * The header a header token is read from when the node doesn't name one.
     */
    public const string DEFAULT_HEADER = 'X-Webhook-Token';

    /**
     * Whether requests are rejected until a secret is set.
     */
    public function needsSecret(): bool
    {
        return $this !== self::None;
    }
}
