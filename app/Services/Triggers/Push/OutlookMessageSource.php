<?php

namespace App\Services\Triggers\Push;

use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;

/**
 * "New email" and calendar changes in Outlook through Microsoft Graph change
 * subscriptions: one on a mail folder, shared by every Outlook mail trigger on
 * that folder, and one on the calendar, shared by every calendar trigger.
 *
 * Mail and event subscriptions last under three days, so they are renewed every two.
 * Graph posts a notification (validated with the watch's secret as
 * `clientState`) and the trigger's own poll fetches the mail.
 */
class OutlookMessageSource extends AbstractWatchedSource
{
    private const string API = 'https://graph.microsoft.com/v1.0';

    /**
     * Graph's longest lifetime for a mail subscription is 4230 minutes.
     */
    private const int LIFETIME_MINUTES = 4200;

    public function provider(): string
    {
        return 'outlook';
    }

    /**
     * The reader nodes that watch the calendar rather than a mail folder.
     */
    private const array CALENDAR_NODES = ['outlook_list_new_events', 'outlook_list_updated_events', 'outlook_list_cancelled_events', 'outlook_list_upcoming_events'];

    protected function nodeTypes(): array
    {
        return ['outlook_list_messages', ...self::CALENDAR_NODES];
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        return in_array($trigger->config['node_type'] ?? null, self::CALENDAR_NODES, true) ? 'events' : 'folder:'.$this->folder($trigger);
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->create($watch);
    }

    /**
     * Extend the subscription; if Graph has forgotten it, make a new one.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $http = $this->http($this->credentialOf($watch));
        $expires = now()->addMinutes(self::LIFETIME_MINUTES);

        try {
            $this->ok($http->patch(self::API.'/subscriptions/'.$watch->external_id, ['expirationDateTime' => $expires->toIso8601ZuluString()]));
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 404) {
                throw $exception;
            }

            $this->create($watch);

            return;
        }

        $watch->expires_at = $expires;
        $watch->renew_at = now()->addDays(2);
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        if ($watch->external_id !== null) {
            $this->http($this->credentialOf($watch))->delete(self::API.'/subscriptions/'.$watch->external_id);
        }
    }

    /**
     * Graph names the subscription in every notification.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        return $watch?->external_id;
    }

    private function create(ProviderWatch $watch): void
    {
        $isCalendar = $watch->resource_key === 'events';
        $folder = str_starts_with($watch->resource_key, 'folder:') ? substr($watch->resource_key, 7) : 'inbox';
        $expires = Carbon::now()->addMinutes(self::LIFETIME_MINUTES);
        $secret = $this->newSecret();

        $body = $this->ok($this->http($this->credentialOf($watch))->post(self::API.'/subscriptions', [
            'changeType' => $isCalendar ? 'created,updated,deleted' : 'created',
            'notificationUrl' => $this->callbackUrl(),
            'lifecycleNotificationUrl' => $this->callbackUrl(),
            'resource' => $isCalendar ? 'me/events' : "me/mailFolders('{$folder}')/messages",
            'expirationDateTime' => $expires->toIso8601ZuluString(),
            'clientState' => $secret,
        ]))->json();

        $watch->external_id = (string) $body['id'];
        $watch->secret = $secret;
        $watch->expires_at = $expires;
        $watch->renew_at = now()->addDays(2);
    }

    private function folder(Trigger $trigger): string
    {
        $folder = $this->nodeConfig($trigger)['folder'] ?? null;

        return is_string($folder) && $folder !== '' ? $folder : 'inbox';
    }
}
