<?php

namespace App\Services\Triggers\Push;

use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Support\Carbon;

/**
 * "New response" in Google Forms through the Forms watch API and a Google
 * Pub/Sub topic.
 *
 * One watch per form and account, shared by every trigger on that form. Forms
 * only says "there is a new response", so the trigger's own poll fetches it.
 * A watch lasts seven days; it is renewed daily.
 *
 * Setup outside the app: a Pub/Sub topic that
 * forms-notifications@system.gserviceaccount.com may publish to, and a push
 * subscription on it calling /api/hooks/apps/google_forms?token={token}.
 */
class GoogleFormsSource extends AbstractWatchedSource
{
    private const string API = 'https://forms.googleapis.com/v1/forms';

    public function provider(): string
    {
        return 'google_forms';
    }

    protected function nodeTypes(): array
    {
        return ['google_forms_list_responses'];
    }

    protected function isConfigured(): bool
    {
        return filled(config('triggers.push.google_forms.topic')) && filled(config('triggers.push.google_forms.verification_token'));
    }

    /**
     * Only a trigger that names a form: one whose form is filled in at run
     * time has nothing to watch, and just keeps polling.
     */
    public function supports(Trigger $trigger): bool
    {
        return parent::supports($trigger) && $this->watchKeyFor($trigger) !== null;
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $id = $this->nodeConfig($trigger)['form_id'] ?? null;

        return is_string($id) && $id !== '' && ! str_contains($id, '{{') ? 'form:'.$id : null;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $formId = $this->formIdOf($watch);

        $body = $this->ok($this->http($this->credentialOf($watch))->post(self::API.'/'.rawurlencode($formId).'/watches', [
            'watch' => [
                'target' => ['topic' => ['topicName' => config('triggers.push.google_forms.topic')]],
                'eventType' => 'RESPONSES',
            ],
        ]))->json();

        $watch->external_id = (string) $body['id'];
        $watch->meta = [...(array) $watch->meta, 'form_id' => $formId];

        $this->schedule($watch, $body);
    }

    /**
     * A watch is extended in place, for another seven days.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $body = $this->ok($this->http($this->credentialOf($watch))->post($this->watchUrl($watch).':renew', new \stdClass))->json();

        $this->schedule($watch, $body);
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $this->ok($this->http($this->credentialOf($watch))->delete($this->watchUrl($watch)));
    }

    /**
     * Pub/Sub messages name the form.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        $id = $watch?->meta['form_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function formIdOf(ProviderWatch $watch): string
    {
        return substr($watch->resource_key, strlen('form:'));
    }

    private function watchUrl(ProviderWatch $watch): string
    {
        return self::API.'/'.rawurlencode((string) ($watch->meta['form_id'] ?? $this->formIdOf($watch))).'/watches/'.rawurlencode((string) $watch->external_id);
    }

    /**
     * @param  array<string, mixed>  $body  Forms' answer: `expireTime` is when the watch stops
     */
    private function schedule(ProviderWatch $watch, array $body): void
    {
        $expires = isset($body['expireTime']) ? Carbon::parse((string) $body['expireTime']) : now()->addDays(7);

        $watch->expires_at = $expires;
        $watch->renew_at = min(now()->addDay(), $expires->copy()->subHours(12));
    }
}
