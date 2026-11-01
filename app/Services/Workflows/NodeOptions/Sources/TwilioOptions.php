<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The account's phone numbers, Messaging Services and Verify services. The
 * connection is an account SID and an auth token, sent as basic auth. Twilio
 * can't search a list, so a search filters what was fetched.
 */
class TwilioOptions extends HttpOptionsSource
{
    private const int PAGE_SIZE = 200;

    public function sources(): array
    {
        return ['twilio.numbers', 'twilio.messaging_services', 'twilio.verify_services'];
    }

    protected function appName(): string
    {
        return 'Twilio';
    }

    /**
     * Basic auth with the account SID (kept on the connection) and its token.
     *
     * @throws ConnectorException
     */
    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        $sid = trim((string) ($query->credentialData['account_sid'] ?? ''));

        if ($sid === '') {
            throw new ConnectorException('Add the Account SID to the Twilio connection to load this list.');
        }

        return Http::withBasicAuth($sid, (string) $query->token)->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return match ($source) {
            'twilio.messaging_services' => $this->services($query, 'https://messaging.twilio.com/v1/Services'),
            'twilio.verify_services' => $this->services($query, 'https://verify.twilio.com/v2/Services'),
            default => $this->numbers($query),
        };
    }

    private function numbers(NodeOptionsQuery $query): NodeOptionsPage
    {
        $sid = trim((string) ($query->credentialData['account_sid'] ?? ''));
        $body = $this->getJson($query, "https://api.twilio.com/2010-04-01/Accounts/{$sid}/IncomingPhoneNumbers.json", ['PageSize' => self::PAGE_SIZE]);

        return new NodeOptionsPage(
            collect($body['incoming_phone_numbers'] ?? [])
                ->filter(fn (mixed $number): bool => is_array($number) && isset($number['phone_number']) && $query->matches((string) $number['phone_number'], (string) ($number['friendly_name'] ?? '')))
                ->map(fn (array $number): NodeOption => new NodeOption((string) $number['phone_number'], (string) $number['phone_number'], ($number['friendly_name'] ?? '') !== $number['phone_number'] ? ($number['friendly_name'] ?? null) : null)),
        );
    }

    private function services(NodeOptionsQuery $query, string $url): NodeOptionsPage
    {
        $body = $this->getJson($query, $url, ['PageSize' => 100]);

        return new NodeOptionsPage(
            collect($body['services'] ?? [])
                ->filter(fn (mixed $service): bool => is_array($service) && isset($service['sid']) && $query->matches((string) ($service['friendly_name'] ?? ''), (string) $service['sid']))
                ->map(fn (array $service): NodeOption => new NodeOption((string) $service['sid'], $service['friendly_name'] ?? null, $service['sid'])),
        );
    }
}
