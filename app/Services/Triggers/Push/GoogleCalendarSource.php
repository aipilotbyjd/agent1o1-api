<?php

namespace App\Services\Triggers\Push;

use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;

/**
 * Events in a Google Calendar through a channel on the calendar, shared by
 * every Calendar trigger on it.
 */
class GoogleCalendarSource extends GoogleChannelSource
{
    public function provider(): string
    {
        return 'google_calendar';
    }

    protected function nodeTypes(): array
    {
        return ['google_calendar_list_events', 'google_calendar_list_updated_events', 'google_calendar_list_cancelled_events', 'google_calendar_list_upcoming_events'];
    }

    protected function api(): string
    {
        return 'https://www.googleapis.com/calendar/v3';
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        return 'calendar:'.$this->calendarId($trigger);
    }

    protected function openChannel(PendingRequest $http, ProviderWatch $watch, array $channel): array
    {
        $calendar = str_starts_with($watch->resource_key, 'calendar:') ? substr($watch->resource_key, 9) : 'primary';

        return $this->ok($http->post($this->api().'/calendars/'.rawurlencode($calendar).'/events/watch', $channel))->json();
    }

    private function calendarId(Trigger $trigger): string
    {
        $id = $this->nodeConfig($trigger)['calendar_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : 'primary';
    }
}
