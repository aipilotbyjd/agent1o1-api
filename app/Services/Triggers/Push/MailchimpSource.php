<?php

namespace App\Services\Triggers\Push;

use App\Exceptions\ConnectorException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Nodes\Integrations\Mailchimp\AbstractMailchimpNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Subscriber changes in a Mailchimp audience through an audience webhook.
 *
 * One webhook per audience and connected account, shared by every trigger on
 * that audience, for subscribes, unsubscribes, profile and address changes and
 * cleaned addresses. Mailchimp does not sign webhooks, so the URL carries a
 * random token (kept hashed); a delivery only makes the trigger's own poll look
 * now. Mailchimp calls the URL once to check it before it accepts it, which the
 * ingress allows because the watch is saved first. An audience holds ten
 * webhooks, and a refusal for that reason is shown as it is.
 */
class MailchimpSource extends AbstractTokenWatchedSource
{
    /**
     * Mailchimp's own words for what a webhook should hear about.
     *
     * @var array<string, bool>
     */
    private const array EVENTS = ['subscribe' => true, 'unsubscribe' => true, 'profile' => true, 'cleaned' => true, 'upemail' => true, 'campaign' => false];

    public function provider(): string
    {
        return 'mailchimp';
    }

    protected function nodeTypes(): array
    {
        return ['mailchimp_list_new_subscribers', 'mailchimp_list_unsubscribes', 'mailchimp_list_cleaned_members', 'mailchimp_list_updated_members'];
    }

    public function supports(Trigger $trigger): bool
    {
        return parent::supports($trigger) && $this->audienceOf($trigger) !== null;
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $audience = $this->audienceOf($trigger);

        return $audience === null ? null : 'list:'.$audience;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Make the audience's webhook and record it on the watch.
     */
    private function register(ProviderWatch $watch): void
    {
        $audience = substr($watch->resource_key, strlen('list:'));

        $this->registerUrl($watch, function (string $url) use ($watch, $audience): void {
            $credential = $this->credentialOf($watch);
            $response = $this->ok($this->mailchimp($credential)->post($this->base($credential)."/lists/{$audience}/webhooks", [
                'url' => $url,
                'events' => self::EVENTS,
                'sources' => ['user' => true, 'admin' => true, 'api' => true],
            ]));

            $watch->external_id = (string) $response->json('id');
        });

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * Mailchimp never expires a webhook, but someone may have deleted it: make it again.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $audience = substr($watch->resource_key, strlen('list:'));
        $credential = $this->credentialOf($watch);
        $response = $this->mailchimp($credential)->get($this->base($credential)."/lists/{$audience}/webhooks/{$watch->external_id}");

        if ($response->status() === 404) {
            $this->register($watch);

            return;
        }

        $this->ok($response);
        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $credential = $this->credentialOf($watch);
        $response = $this->mailchimp($credential)->delete($this->base($credential).'/lists/'.substr($watch->resource_key, strlen('list:'))."/webhooks/{$watch->external_id}");

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Mailchimp takes basic auth with the API key as the password.
     */
    protected function mailchimp(ConnectorCredential $credential): PendingRequest
    {
        return Http::withBasicAuth('flows', $this->tokens->accessToken($credential))->acceptJson()->timeout(20);
    }

    /**
     * The API host, from the data center the key ends with.
     *
     * @throws ConnectorException when the key has none
     */
    private function base(ConnectorCredential $credential): string
    {
        try {
            return AbstractMailchimpNode::baseUrl($this->tokens->accessToken($credential, markUsed: false));
        } catch (InvalidArgumentException $exception) {
            throw new ConnectorException($exception->getMessage());
        }
    }

    /**
     * The audience a trigger names outright.
     */
    private function audienceOf(Trigger $trigger): ?string
    {
        $value = trim((string) ($this->nodeConfig($trigger)['list_id'] ?? ''));

        return preg_match('/^[0-9a-f]{10}$/i', $value) === 1 ? $value : null;
    }
}
