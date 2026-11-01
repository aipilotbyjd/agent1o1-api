<?php

namespace App\Services\Triggers\Push;

use App\Contracts\SupportsTriggerMode;
use App\Contracts\Triggers\ItemPushSource;
use App\Exceptions\ConnectorException;
use App\Exceptions\WatchRefusedException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Services\Connectors\ConnectorTokens;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Instant "text received" for a Twilio number, through the number's own
 * incoming-message URL.
 *
 * Twilio sends a text to one URL per number, and a number has room for one. The
 * app sets that URL itself (with the account's own credentials) and puts the old
 * one back when the trigger goes away. A number that already forwards texts to
 * an application of its own is refused rather than taken over, and so is one
 * that uses a TwiML app: the trigger then reads the account every minute
 * instead, which is how it works without this. Twilio's untouched default URL
 * (its demo reply) is not worth keeping and is replaced.
 *
 * Only a text to a named number can be instant. WhatsApp senders have their
 * webhook set in the Console, and a trigger on every number has nothing to
 * register: both stay on the timer.
 */
class TwilioSource extends AbstractWatchedSource implements ItemPushSource
{
    private const string API = 'https://api.twilio.com/2010-04-01/Accounts';

    /**
     * The URL a new Twilio number answers texts with until someone changes it.
     */
    private const string DEMO_URL = 'https://demo.twilio.com/welcome/sms/reply';

    public function __construct(
        ConnectorTokens $tokens,
        private readonly NodeRegistry $registry,
    ) {
        parent::__construct($tokens);
    }

    public function provider(): string
    {
        return 'twilio';
    }

    protected function nodeTypes(): array
    {
        return ['twilio_list_new_incoming_messages'];
    }

    /**
     * Only a text trigger on one number the account holds.
     */
    public function supports(Trigger $trigger): bool
    {
        return parent::supports($trigger) && $this->numberOf($trigger) !== null;
    }

    /**
     * One URL per number: triggers on the same number share it.
     */
    public function watchKeyFor(Trigger $trigger): ?string
    {
        return $this->numberOf($trigger);
    }

    /**
     * Point the number's incoming-message URL at us, remembering what it was.
     *
     * @throws WatchRefusedException when the account has no such number, or it already answers texts itself
     */
    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        [$sid, $token] = $this->login($this->credentialOf($watch));
        $number = $watch->resource_key;

        $found = $this->api($sid, $token, 'GET', "/{$sid}/IncomingPhoneNumbers.json", ['PhoneNumber' => $number]);
        $record = (array) data_get($found, 'incoming_phone_numbers.0', []);

        if (($record['sid'] ?? null) === null) {
            throw new WatchRefusedException("This Twilio account has no phone number {$number}. Pick one of its numbers.");
        }

        $current = (string) ($record['sms_url'] ?? '');

        if (($record['sms_application_sid'] ?? '') !== '' && $record['sms_application_sid'] !== null) {
            throw new WatchRefusedException("The number {$number} answers texts with a TwiML app, and a number can have only one. Remove the app from the number to get instant delivery.");
        }

        if ($current !== '' && ! $this->isOurs($current) && ! str_starts_with($current, self::DEMO_URL)) {
            throw new WatchRefusedException("The number {$number} already sends incoming texts to another address (".(parse_url($current, PHP_URL_HOST) ?: 'unknown').'), and it can have only one. Clear it in the Twilio Console to get instant delivery.');
        }

        $taken = ProviderWatch::query()->where('provider', $this->provider())->where('external_id', $number)
            ->when($watch->exists, fn ($query) => $query->whereKeyNot($watch->getKey()))->exists();

        if ($taken) {
            throw new WatchRefusedException("The number {$number} is already used for instant texts through another connected account.");
        }

        // Twilio posts a delivery to the URL the moment it is set; the ingress finds the account on the watch.
        $existed = $watch->exists;
        $meta = (array) $watch->meta;
        $watch->forceFill([
            'external_id' => $number,
            // Keep what the number did before only the first time: a repeat must not remember our own URL.
            'meta' => ['number_sid' => $record['sid'], 'account_sid' => $sid, ...($this->isOurs($current) ? Arr::only($meta, ['previous_sms_url', 'previous_sms_method']) : ['previous_sms_url' => $current, 'previous_sms_method' => $record['sms_method'] ?? 'POST'])],
        ])->save();

        try {
            $this->api($sid, $token, 'POST', "/{$sid}/IncomingPhoneNumbers/{$record['sid']}.json", ['SmsUrl' => $this->url(), 'SmsMethod' => 'POST']);
        } catch (Throwable $exception) {
            $existed ? $watch->forceFill(['external_id' => null])->save() : $watch->delete();

            throw $exception;
        }

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * Twilio never expires the URL, but someone may have changed it.
     *
     * @throws ConnectorException when the number now sends texts elsewhere
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        [$sid, $token] = $this->login($this->credentialOf($watch));
        $number = (array) $this->api($sid, $token, 'GET', "/{$sid}/IncomingPhoneNumbers/".($watch->meta['number_sid'] ?? '').'.json');
        $current = (string) ($number['sms_url'] ?? '');

        if ($current !== '' && ! $this->isOurs($current)) {
            throw new ConnectorException("The number {$watch->resource_key} now sends incoming texts to another address, so its triggers fall back to checking every minute. Clear it in the Twilio Console to get instant delivery again.");
        }

        if ($current === '') {
            $this->api($sid, $token, 'POST', "/{$sid}/IncomingPhoneNumbers/{$number['sid']}.json", ['SmsUrl' => $this->url(), 'SmsMethod' => 'POST']);
        }

        $watch->renew_at = now()->addDay();
    }

    /**
     * Put back what the number did before, if the URL is still ours.
     */
    public function deleteWatch(ProviderWatch $watch): void
    {
        [$sid, $token] = $this->login($this->credentialOf($watch));
        $numberSid = (string) ($watch->meta['number_sid'] ?? '');

        if ($numberSid === '') {
            return;
        }

        $number = (array) $this->api($sid, $token, 'GET', "/{$sid}/IncomingPhoneNumbers/{$numberSid}.json");

        if ($this->isOurs((string) ($number['sms_url'] ?? ''))) {
            $this->api($sid, $token, 'POST', "/{$sid}/IncomingPhoneNumbers/{$numberSid}.json", ['SmsUrl' => (string) ($watch->meta['previous_sms_url'] ?? ''), 'SmsMethod' => (string) ($watch->meta['previous_sms_method'] ?? 'POST')]);
        }
    }

    /**
     * The number the texts are for: every delivery names it in `To`.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        return $watch?->external_id;
    }

    /**
     * Whether the message is for this trigger's number and kind.
     */
    public function accepts(Trigger $trigger, array $item): bool
    {
        $node = $this->registry->resolve((string) ($trigger->config['node_type'] ?? ''));

        return $node instanceof SupportsTriggerMode
            && $node->triggerItems(['messages' => [$item]], $this->nodeConfig($trigger)) !== [];
    }

    /**
     * A received text as the message list would show it.
     */
    public function itemFor(array $event): array
    {
        return array_filter([
            'sid' => $event['MessageSid'] ?? $event['SmsMessageSid'] ?? null,
            'status' => $event['SmsStatus'] ?? 'received',
            'direction' => 'inbound',
            'from' => $event['From'] ?? null,
            'to' => $event['To'] ?? null,
            'body' => $event['Body'] ?? '',
            'num_segments' => isset($event['NumSegments']) ? (int) $event['NumSegments'] : null,
            'num_media' => (int) ($event['NumMedia'] ?? 0),
            'media_urls' => $this->media($event, 'MediaUrl'),
            'media_types' => $this->media($event, 'MediaContentType'),
            'messaging_service_sid' => $event['MessagingServiceSid'] ?? null,
            'channel' => str_starts_with((string) ($event['To'] ?? ''), 'whatsapp:') ? 'whatsapp' : 'sms',
            'from_city' => $event['FromCity'] ?? null,
            'from_state' => $event['FromState'] ?? null,
            'from_country' => $event['FromCountry'] ?? null,
            'from_zip' => $event['FromZip'] ?? null,
            'date_created' => now()->toIso8601String(),
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * The message SID, as the poll names it.
     */
    public function itemId(array $item): string
    {
        return (string) ($item['sid'] ?? '');
    }

    /**
     * The E.164 number of a text trigger, or null when it has none to register on.
     */
    private function numberOf(Trigger $trigger): ?string
    {
        $config = $this->nodeConfig($trigger);
        $number = (string) preg_replace('/[\s().-]+/', '', (string) ($config['to'] ?? ''));

        return ($config['channel'] ?? 'sms') === 'sms' && preg_match('/^\+[1-9]\d{6,14}$/', $number) === 1 ? $number : null;
    }

    /**
     * The files of a delivery (`MediaUrl0`, `MediaUrl1`, …) as a list.
     *
     * @param  array<string, mixed>  $event
     * @return list<string>
     */
    private function media(array $event, string $prefix): array
    {
        $values = [];

        for ($index = 0; $index < (int) ($event['NumMedia'] ?? 0); $index++) {
            if (isset($event["{$prefix}{$index}"])) {
                $values[] = (string) $event["{$prefix}{$index}"];
            }
        }

        return $values;
    }

    /**
     * The account's SID and auth token.
     *
     * @return array{0: string, 1: string}
     *
     * @throws ConnectorException
     */
    private function login(ConnectorCredential $credential): array
    {
        $sid = trim((string) ($credential->data['account_sid'] ?? ''));
        $token = trim((string) ($credential->data['api_key'] ?? ''));

        return $sid !== '' && $token !== '' ? [$sid, $token] : throw new ConnectorException('The Twilio connection needs the Account SID and Auth Token. Reconnect it.');
    }

    private function url(): string
    {
        return $this->callbackUrl();
    }

    private function isOurs(string $url): bool
    {
        return $url !== '' && str_starts_with($url, $this->callbackUrl());
    }

    /**
     * One call to Twilio's account API as the account.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws ConnectorException when Twilio refuses the credentials
     * @throws WatchRefusedException when it refuses the request in words
     */
    private function api(string $sid, string $token, string $method, string $path, array $params = []): array
    {
        $request = Http::withBasicAuth($sid, $token)->acceptJson()->timeout(20);

        try {
            $response = $method === 'GET'
                ? ($params === [] ? $request->get(self::API.$path) : $request->get(self::API.$path, $params))
                : $request->asForm()->post(self::API.$path, $params);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Twilio could not be reached: '.$exception->getMessage());
        }

        if ($response->status() === 401) {
            throw new ConnectorException('Twilio refused the Account SID or Auth Token. Reconnect it.');
        }

        if ($response->successful()) {
            return (array) ($response->json() ?? []);
        }

        $message = (string) ($response->json('message') ?? "HTTP {$response->status()}");

        // A 4xx is Twilio's answer to what we asked and repeats; a 429 or 5xx is worth another try.
        throw $response->status() >= 400 && $response->status() < 500 && $response->status() !== 429
            ? new WatchRefusedException("Twilio said: {$message}.")
            : new RuntimeException("Twilio error: {$message}.");
    }
}
