<?php

namespace App\Services\Triggers\Push;

use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;

/**
 * "New response" in Typeform through a webhook on the form.
 *
 * One webhook per form and connected account, shared by every trigger on that
 * form, registered with its own tag and secret. Typeform signs each delivery
 * (`Typeform-Signature`, an HMAC-SHA256 of the body in base64) and names the form
 * in it; the delivery only makes the trigger's own poll look now, so the poll's
 * filters, order and dedupe still decide what runs. Typeform switches a webhook
 * off after repeated failures, so the daily renewal turns it back on.
 *
 * Only a trigger that names its form can be watched: one whose form comes from an
 * earlier step keeps polling.
 */
class TypeformSource extends AbstractWatchedSource
{
    public function provider(): string
    {
        return 'typeform';
    }

    protected function nodeTypes(): array
    {
        return ['typeform_list_responses'];
    }

    public function supports(Trigger $trigger): bool
    {
        return parent::supports($trigger) && $this->formOf($trigger) !== null;
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $form = $this->formOf($trigger);

        return $form === null ? null : 'form:'.$form;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $form = substr($watch->resource_key, strlen('form:'));
        $tag = 'flows-'.Str::lower(Str::random(8));

        $this->put($watch, $form, $tag, $this->newSecret());

        $watch->external_id = $form;
        $watch->meta = [...(array) $watch->meta, 'tag' => $tag];
        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * Typeform turns a webhook off after failed deliveries (or someone removed
     * it): look at it, and put it back as it was.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $form = (string) $watch->external_id;
        $tag = (string) ($watch->meta['tag'] ?? '');
        $credential = $this->credentialOf($watch);

        $response = $this->http($credential)->get($this->base($credential)."/forms/{$form}/webhooks/{$tag}");

        if ($response->status() === 404 || ($response->successful() && $response->json('enabled') === false)) {
            $this->put($watch, $form, $tag, (string) $watch->secret);
        } else {
            $this->ok($response);
        }

        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $credential = $this->credentialOf($watch);
        $response = $this->http($credential)->delete($this->base($credential).'/forms/'.$watch->external_id.'/webhooks/'.($watch->meta['tag'] ?? ''));

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Deliveries name the form.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        return $watch?->external_id;
    }

    /**
     * Create or update the form's webhook.
     *
     * @throws RequestException when Typeform refuses it
     */
    private function put(ProviderWatch $watch, string $form, string $tag, string $secret): void
    {
        $credential = $this->credentialOf($watch);

        $this->ok($this->http($credential)->put($this->base($credential)."/forms/{$form}/webhooks/{$tag}", [
            'url' => $this->callbackUrl(),
            'enabled' => true,
            'secret' => $secret,
            'verify_ssl' => true,
            'event_types' => ['form_response' => true],
        ]));

        $watch->secret = $secret;
    }

    /**
     * The API host of the account's data center.
     */
    private function base(ConnectorCredential $credential): string
    {
        return strtolower((string) ($credential->data['region'] ?? '')) === 'eu' ? 'https://api.eu.typeform.com' : 'https://api.typeform.com';
    }

    /**
     * The form a trigger names, when it names one outright.
     */
    private function formOf(Trigger $trigger): ?string
    {
        $value = trim((string) ($this->nodeConfig($trigger)['form_id'] ?? ''));

        if ($value === '' || str_contains($value, '{{')) {
            return null;
        }

        if (preg_match('~/(?:to|forms)/([A-Za-z0-9]+)~', $value, $match) === 1) {
            $value = $match[1];
        }

        return preg_match('/^[A-Za-z0-9]{4,20}$/', $value) === 1 ? $value : null;
    }
}
