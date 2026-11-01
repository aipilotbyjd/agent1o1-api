<?php

namespace App\Nodes\Integrations\Twilio;

use App\Contracts\SupportsTriggerMode;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * "Message received". Reads the account's received messages every minute; for
 * a text to one of the account's own numbers it is also instant (the number's
 * incoming-message URL, see `TwilioSource`). WhatsApp messages are read on the
 * timer only: a WhatsApp sender's webhook is set in the Twilio Console, not
 * through the API.
 */
class TwilioListNewIncomingMessagesNode extends TwilioListMessagesNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * Pictures and files on a message are fetched for the first ones only.
     */
    private const int MAX_MEDIA = 10;

    public function type(): string
    {
        return 'twilio_list_new_incoming_messages';
    }

    public function name(): string
    {
        return 'Twilio: List Received Messages';
    }

    public function description(): string
    {
        return 'Lists the texts or WhatsApp messages the account received, newest first. As a trigger it starts the flow for each new message: instantly for a text to one of your numbers, within a minute otherwise.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'to' => Field::dynamic('Received on', 'twilio.numbers', 'Only messages to this number. Leave empty for every number (those are read on a timer). For WhatsApp, your WhatsApp number.', '+14155550123'),
                'channel' => Field::select('Kind', ['sms' => 'Texts (SMS and MMS)', 'whatsapp' => 'WhatsApp', 'any' => 'Both'], null, 'sms'),
                'limit' => Field::integer('Max results', null, 20, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = (string) ($config['channel'] ?? 'sms');
        $to = trim((string) ($config['to'] ?? ''));

        // A WhatsApp number is addressed with its prefix.
        if ($to !== '' && $channel === 'whatsapp' && ! str_starts_with($to, 'whatsapp:')) {
            $to = 'whatsapp:'.$to;
        }

        $messages = $this->readMessages($run, [...$config, 'to' => $to, 'direction' => 'inbound', 'status' => 'any', 'from' => null]);
        $messages = array_values(array_filter(array_map($this->shapeMessage(...), $messages), fn (array $message): bool => $channel === 'any' || $channel === '' || $message['channel'] === $channel));

        return ['messages' => $messages, 'count' => count($messages)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'messages';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['sid'] ?? '');
    }

    /**
     * The pictures and files of a new message, which the list does not carry.
     */
    public function expandTriggerItem(Run $run, array $config, array $item): array
    {
        if (($item['num_media'] ?? 0) < 1 || ! isset($item['sid']) || isset($item['media_urls'])) {
            return $item;
        }

        [$account] = $this->account($run, $config);
        $media = (array) data_get((array) $this->twilio($run, $config, 'GET', "/Messages/{$item['sid']}/Media.json"), 'media_list', []);

        return [...$item, 'media_urls' => array_slice(array_values(array_map(
            fn (array $file): string => 'https://api.twilio.com'.preg_replace('/\.json$/', '', (string) ($file['uri'] ?? "/2010-04-01/Accounts/{$account}/Messages/{$item['sid']}/Media/{$file['sid']}.json")),
            array_filter($media, 'is_array'),
        )), 0, self::MAX_MEDIA), 'media_types' => array_slice(array_values(array_map(fn (array $file): ?string => $file['content_type'] ?? null, array_filter($media, 'is_array'))), 0, self::MAX_MEDIA)];
    }

    public function triggerName(): string
    {
        return 'Twilio: Message received';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a text or WhatsApp message arrives. Instant for a text to a number you pick.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }

    public function triggerPollMinutes(): int
    {
        return 1;
    }

    /**
     * Twilio lists newest first.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
