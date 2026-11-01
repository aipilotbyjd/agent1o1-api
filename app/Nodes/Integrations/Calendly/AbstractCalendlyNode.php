<?php

namespace App\Nodes\Integrations\Calendly;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\CallsApiKeyService;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Shared by the Calendly nodes. A Calendly personal access token is the
 * login; almost every call needs the signed-in person's own address
 * (`users/me` says what it is), which is looked up once and kept for a few
 * minutes.
 */
abstract class AbstractCalendlyNode implements DeclaresEffect, DeclaresOutputSchema, NodeContract
{
    use CallsApiKeyService;

    protected const string API_URL = 'https://api.calendly.com';

    public function category(): string
    {
        return 'calendly';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    protected function serviceName(): string
    {
        return 'Calendly';
    }

    protected function authenticate(string $key, array $headers, array $query): array
    {
        return [[...$headers, 'Authorization' => 'Bearer '.$key], $query];
    }

    /**
     * The signed-in person: their Calendly address, name and timezone.
     *
     * @param  array<string, mixed>  $config
     * @return array{uri: string, name: ?string, email: ?string, timezone: ?string, scheduling_url: ?string, organization: ?string}
     */
    protected function me(Run $run, array $config): array
    {
        return Cache::remember('calendly:me:'.sha1($this->resolveAccessToken($run, $config)), 300, function () use ($run, $config): array {
            $user = (array) ($this->call($run, $config, 'GET', self::API_URL.'/users/me')['resource'] ?? []);

            return [
                'uri' => (string) ($user['uri'] ?? ''),
                'name' => $user['name'] ?? null,
                'email' => $user['email'] ?? null,
                'timezone' => $user['timezone'] ?? null,
                'scheduling_url' => $user['scheduling_url'] ?? null,
                'organization' => $user['current_organization'] ?? null,
            ];
        });
    }

    /**
     * The ID Calendly uses for an event or event type: the last part of its
     * address, whether the whole address or only the ID was given.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function idFrom(array $config, string $key, string $what): string
    {
        $value = trim((string) ($config[$key] ?? ''));
        $id = basename(rtrim((string) parse_url($value, PHP_URL_PATH), '/')) ?: $value;

        if (preg_match('/^[A-Za-z0-9_-]{8,64}$/', $id) !== 1) {
            throw new InvalidArgumentException("That is not {$what}. Use the ID, or the whole address, from the list node.");
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    protected function shapeEvent(array $event): array
    {
        return [
            'event_id' => basename((string) ($event['uri'] ?? '')) ?: null,
            'name' => $event['name'] ?? null,
            'status' => $event['status'] ?? null,
            'start_time' => $event['start_time'] ?? null,
            'end_time' => $event['end_time'] ?? null,
            'event_type' => isset($event['event_type']) ? basename((string) $event['event_type']) : null,
            'location' => $event['location']['location'] ?? $event['location']['join_url'] ?? null,
            'join_url' => $event['location']['join_url'] ?? null,
            'location_type' => $event['location']['type'] ?? null,
            'invitees' => $event['invitees_counter']['active'] ?? null,
            'hosts' => array_values(array_filter(array_map(fn (mixed $member): ?string => $member['user_email'] ?? null, (array) ($event['event_memberships'] ?? [])))),
            'created_at' => $event['created_at'] ?? null,
            'cancel_reason' => $event['cancellation']['reason'] ?? null,
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'event_id' => ['title' => 'Event ID', 'description' => 'Calendly’s ID for the booked meeting.'],
                'name' => ['title' => 'Name', 'description' => 'The event type’s name, e.g. 30 Minute Meeting.'],
                'status' => ['title' => 'Status', 'description' => 'active or canceled.'],
                'start_time' => ['title' => 'Starts at', 'description' => 'When it starts (UTC).'],
                'end_time' => ['title' => 'Ends at', 'description' => 'When it ends (UTC).'],
                'join_url' => ['title' => 'Join link', 'description' => 'The video link, when the event has one.'],
                'hosts' => ['title' => 'Hosts', 'description' => 'The hosts’ email addresses.'],
            ],
        ];
    }
}
