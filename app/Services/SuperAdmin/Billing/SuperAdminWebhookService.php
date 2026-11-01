<?php

namespace App\Services\SuperAdmin\Billing;

use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Models\Billing\ProcessedWebhookEvent;
use App\Models\Billing\StripeWebhookFailure;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Throwable;

/**
 * Replays a Stripe event whose handler failed. The event is fetched from
 * Stripe again (Stripe keeps them for 30 days) and run through the very same
 * controller a live delivery uses, so it gets the same idempotency check and
 * the same handlers: nothing about fulfilment is duplicated here.
 */
class SuperAdminWebhookService
{
    public function __construct(
        private readonly StripeEvents $events,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    /**
     * @return array{event_id: string, type: string}
     */
    public function replay(SuperAdmin $actor, string $eventId, string $reason): array
    {
        abort_if(
            ProcessedWebhookEvent::query()->where('stripe_event_id', $eventId)->exists(),
            409,
            'This event was already processed, so replaying it would do nothing.',
        );

        $event = $this->fetch($eventId);

        $request = Request::create('/api/stripe/webhook', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($event));

        try {
            // Called directly rather than through the router, so Cashier's
            // signature middleware does not apply: this body came from
            // Stripe's API using our key, not from the open internet.
            $response = app(StripeWebhookController::class)->handleWebhook($request);
        } catch (Throwable $e) {
            report($e);

            abort(502, 'The handler failed again: '.str($e->getMessage())->limit(200, ''));
        }

        abort_if($response->getStatusCode() >= 400, 502, 'The handler answered HTTP '.$response->getStatusCode().'.');

        $this->audit->record(
            $actor,
            'billing.webhook_replayed',
            StripeWebhookFailure::query()->where('stripe_event_id', $eventId)->first(),
            null,
            ['event_id' => $eventId, 'type' => $event['type'] ?? 'unknown'],
            $reason,
        );

        return ['event_id' => $eventId, 'type' => $event['type'] ?? 'unknown'];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $eventId): array
    {
        try {
            return $this->events->retrieve($eventId);
        } catch (InvalidRequestException) {
            abort(404, 'Stripe has no such event (it keeps events for 30 days).');
        } catch (ApiErrorException) {
            abort(502, 'Stripe could not be reached. Try again in a moment.');
        }
    }
}
