<?php

namespace App\Engine\Nodes\Apps\Google;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class GoogleCalendarNode extends AppNode
{
    public const TYPE = 'google_calendar';

    private const BASE_URL = 'https://www.googleapis.com/calendar/v3';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'list_events' => $this->listEvents($input),
            'get_event' => $this->getEvent($input),
            'create_event' => $this->createEvent($input),
            'update_event' => $this->updateEvent($input),
            'delete_event' => $this->deleteEvent($input),
            'list_calendars' => $this->listCalendars($input),
            default => $this->fail("GoogleCalendar: unknown operation '{$operation}'"),
        };
    }

    private function listEvents(NodeInput $input): NodeResult
    {
        $calendarId = $input->config['calendar_id'] ?? 'primary';
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get("/calendars/{$calendarId}/events", [
                'timeMin' => $input->config['time_min'] ?? now()->toRfc3339String(),
                'maxResults' => $input->config['max_results'] ?? 10,
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleCalendar list_events failed: {$response->body()}");
    }

    private function getEvent(NodeInput $input): NodeResult
    {
        $calendarId = $input->config['calendar_id'] ?? 'primary';
        $eventId = $input->config['event_id'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get("/calendars/{$calendarId}/events/{$eventId}");

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleCalendar get_event failed: {$response->body()}");
    }

    private function createEvent(NodeInput $input): NodeResult
    {
        $calendarId = $input->config['calendar_id'] ?? 'primary';
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post("/calendars/{$calendarId}/events", [
                'summary' => $input->config['title'],
                'description' => $input->config['description'] ?? '',
                'start' => ['dateTime' => $input->config['start'], 'timeZone' => $input->config['timezone'] ?? 'UTC'],
                'end' => ['dateTime' => $input->config['end'], 'timeZone' => $input->config['timezone'] ?? 'UTC'],
                'attendees' => array_map(fn ($e) => ['email' => $e], (array) ($input->config['attendees'] ?? [])),
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleCalendar create_event failed: {$response->body()}");
    }

    private function updateEvent(NodeInput $input): NodeResult
    {
        $calendarId = $input->config['calendar_id'] ?? 'primary';
        $eventId = $input->config['event_id'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->patch("/calendars/{$calendarId}/events/{$eventId}", [
                'summary' => $input->config['title'] ?? null,
                'description' => $input->config['description'] ?? null,
                'start' => isset($input->config['start']) ? ['dateTime' => $input->config['start'], 'timeZone' => $input->config['timezone'] ?? 'UTC'] : null,
                'end' => isset($input->config['end']) ? ['dateTime' => $input->config['end'], 'timeZone' => $input->config['timezone'] ?? 'UTC'] : null,
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleCalendar update_event failed: {$response->body()}");
    }

    private function deleteEvent(NodeInput $input): NodeResult
    {
        $calendarId = $input->config['calendar_id'] ?? 'primary';
        $eventId = $input->config['event_id'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->delete("/calendars/{$calendarId}/events/{$eventId}");

        return $response->successful()
            ? $this->success(['deleted' => true])
            : $this->fail("GoogleCalendar delete_event failed: {$response->body()}");
    }

    private function listCalendars(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get('/users/me/calendarList');

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleCalendar list_calendars failed: {$response->body()}");
    }
}
