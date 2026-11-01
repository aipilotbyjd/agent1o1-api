<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared request/error-handling for the Google Calendar node family.
 * Resolves the access token via `ResolvesConnectorCredential`: the same
 * workspace-scoped `ConnectorCredential` (`gmail` connector, whose OAuth
 * scopes cover every Google product node) used by `AbstractGmailNode`.
 */
abstract class AbstractGoogleCalendarNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string BASE_URL = 'https://www.googleapis.com/calendar/v3';

    public function category(): string
    {
        return 'google_calendar';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function get(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'get', $endpoint, $config, $params);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function post(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'post', $endpoint, $config, $params);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function patch(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'patch', $endpoint, $config, $params);
    }

    /**
     * `/calendars/{id}/events` (and `/{eventId}` when given), both segments
     * URL-encoded. A calendar defaults to the primary one.
     *
     * @param  array<string, mixed>  $config
     */
    protected function eventsPath(array $config, ?string $eventId = null): string
    {
        $path = '/calendars/'.rawurlencode((string) (($config['calendar_id'] ?? '') ?: 'primary')).'/events';

        return $eventId === null ? $path : $path.'/'.rawurlencode($eventId);
    }

    /**
     * Events from `/calendars/{id}/events`, as the List Events action and the
     * event triggers read them.
     *
     * Settings beyond the node's fields, set by a trigger when it reads
     * (triggerReadConfig) and by the nodes built on this one:
     * `updated_min` (only events changed since), `time_max`, `show_deleted`
     * (cancelled events too), `single_events` (recurring events expanded),
     * `order_by`, and `ignore_time_min` (no lower time bound, so past events
     * that were changed or cancelled show up).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function listEvents(Run $run, array $config): array
    {
        $singleEvents = (bool) ($config['single_events'] ?? true);
        $ignoreTimeMin = (bool) ($config['ignore_time_min'] ?? false);

        return $this->get($run, $this->eventsPath($config), $config, [
            'timeMin' => $ignoreTimeMin ? null : ($config['time_min'] ?? now()->toRfc3339String()),
            'timeMax' => $config['time_max'] ?? null,
            'updatedMin' => $config['updated_min'] ?? null,
            'showDeleted' => ($config['show_deleted'] ?? false) ? 'true' : null,
            'maxResults' => $config['max_results'] ?? 10,
            'singleEvents' => $singleEvents ? 'true' : 'false',
            // Calendar only sorts by start time when recurring events are expanded.
            'orderBy' => ($config['order_by'] ?? null) ?: ($singleEvents ? 'startTime' : 'updated'),
        ]);
    }

    /**
     * When an event starts; null for one without a start.
     *
     * @param  array<string, mixed>  $event
     */
    protected function eventStart(array $event): ?CarbonImmutable
    {
        $start = $event['start']['dateTime'] ?? $event['start']['date'] ?? null;

        return is_string($start) && $start !== '' ? CarbonImmutable::parse($start) : null;
    }

    /**
     * Seconds between an event's creation and its last change: how long it
     * has been edited since it was made. A fresh event has about none.
     *
     * @param  array<string, mixed>  $event
     */
    protected function secondsEdited(array $event): int
    {
        if (! isset($event['created'], $event['updated'])) {
            return 0;
        }

        return max(0, CarbonImmutable::parse((string) $event['created'])->diffInSeconds(CarbonImmutable::parse((string) $event['updated']), true));
    }

    /**
     * The event fields the user filled in, in the shape Calendar takes. A
     * field left empty is left out, so an update changes only what was set.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function eventBody(array $config): array
    {
        $body = [];

        foreach (['summary', 'description', 'location'] as $field) {
            if (isset($config[$field]) && trim((string) $config[$field]) !== '') {
                $body[$field] = (string) $config[$field];
            }
        }

        foreach (['start' => 'start_at', 'end' => 'end_at'] as $side => $field) {
            if (isset($config[$field]) && trim((string) $config[$field]) !== '') {
                $body[$side] = $this->eventTime((string) $config[$field], $config);
            }
        }

        $attendees = self::emailList($config['attendees'] ?? '');

        if ($attendees !== []) {
            $body['attendees'] = array_map(fn (string $email): array => ['email' => $email], $attendees);
        }

        return $body;
    }

    /**
     * A start or end: a date for an all-day event (its end is exclusive, so
     * the day after the last day), a time with the zone otherwise.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    private function eventTime(string $value, array $config): array
    {
        if ($config['all_day'] ?? false) {
            return ['date' => substr(trim($value), 0, 10)];
        }

        $zone = trim((string) ($config['time_zone'] ?? ''));

        return array_filter(['dateTime' => trim($value), 'timeZone' => $zone !== '' ? $zone : null]);
    }

    /**
     * Email addresses from a comma, semicolon or whitespace separated string
     * (or a list), lower-cased, without repeats.
     *
     * @return list<string>
     */
    public static function emailList(mixed $value): array
    {
        $parts = is_array($value) ? $value : preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $email): string => mb_strtolower(trim((string) $email)), (array) $parts),
            fn (string $email): bool => $email !== '',
        )));
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function delete(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'delete', $endpoint, $config, $params);
    }

    /**
     * Calendar signals failure via a normal HTTP status code, with the error
     * detail under `error.message` in the JSON body, same as Gmail/Drive. A
     * successful delete returns an empty body, which `json()` decodes to
     * `null`.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(Run $run, string $method, string $endpoint, array $config, array $params): array
    {
        $token = $this->resolveAccessToken($run, $config);

        $request = Http::withToken($token);
        $response = match ($method) {
            'get' => $request->get(self::BASE_URL.$endpoint, $params),
            // A delete takes its options in the URL: Laravel would send them as a body.
            'delete' => $request->delete(self::BASE_URL.$endpoint.($params === [] ? '' : '?'.http_build_query($params))),
            // An empty body must be `{}`: `[]` is not a JSON object.
            'patch' => $request->withBody(json_encode($params === [] ? new \stdClass : $params), 'application/json')->patch(self::BASE_URL.$endpoint),
            default => $request->withBody(json_encode($params === [] ? new \stdClass : $params), 'application/json')->post(self::BASE_URL.$endpoint),
        };

        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new RuntimeException("Google Calendar API error [{$endpoint}]: {$message}");
        }

        return $response->json() ?? [];
    }
}
