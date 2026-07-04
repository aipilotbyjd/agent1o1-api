<?php

namespace App\Http\Controllers\Webhooks;

use App\Engine\Trigger\NormalizerRegistry;
use App\Engine\Trigger\TriggerEventDispatcher;
use App\Engine\Webhook\WebhookRegistrarRegistry;
use App\Engine\Webhook\WebhookRegistry;
use App\Http\Controllers\Controller;
use App\Models\Trigger;
use App\Services\TriggerRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TriggerWebhookController extends Controller
{
    public function __construct(
        private readonly WebhookRegistry $registry,
        private readonly TriggerEventDispatcher $dispatcher,
        private readonly TriggerRegistrationService $registration,
        private readonly NormalizerRegistry $normalizers,
    ) {}

    /**
     * Receive an external webhook for a trigger and queue it as a trigger event.
     *
     * Generic webhooks use a shared-secret HMAC of the raw body. App-specific
     * providers (github, slack, stripe, …) verify with their own signature
     * scheme, normalize the payload, and deduplicate by delivery id.
     */
    public function receive(Request $request, string $webhookUuid): JsonResponse
    {
        $trigger = $this->registry->findByUuid($webhookUuid);

        if (! $trigger) {
            return $this->errorResponse('Webhook not found.', 404);
        }

        $provider = $trigger->webhook_provider;

        if ($trigger->isAppSpecific() && WebhookRegistrarRegistry::supports($provider)) {
            return $this->receiveAppSpecific($request, $trigger, $provider);
        }

        return $this->receiveGeneric($request, $trigger);
    }

    private function receiveGeneric(Request $request, Trigger $trigger): JsonResponse
    {
        $signature = $request->header('X-Webhook-Signature');

        if (! $this->registry->verifySignature($trigger, $request->getContent(), $signature)) {
            return $this->errorResponse('Invalid signature.', 401);
        }

        $event = $this->dispatcher->dispatch($trigger, [
            'body' => $request->json()->all() ?: $request->all(),
            'headers' => $this->safeHeaders($request),
            'query' => $request->query(),
            'received_at' => now()->toISOString(),
        ]);

        return $this->successResponse('Webhook received.', ['event_id' => $event?->id], 202);
    }

    private function receiveAppSpecific(Request $request, Trigger $trigger, string $provider): JsonResponse
    {
        // Slack URL verification challenge — respond immediately, no event.
        if ($request->json('type') === 'url_verification') {
            return response()->json(['challenge' => $request->json('challenge')]);
        }

        // Discord PING (type=1) — must ACK synchronously to pass endpoint validation.
        if ($provider === 'discord' && (int) $request->json('type') === 1) {
            return response()->json(['type' => 1]);
        }

        $signature = $this->extractSignature($provider, $request);

        if (! $this->registration->verifyWebhookSignature($trigger, $request->getContent(), $signature ?? '')) {
            return $this->errorResponse('Invalid signature.', 401);
        }

        $payload = $request->json()->all() ?: $request->all();
        $providerEvent = $this->extractProviderEvent($trigger, $provider, $request);
        $deliveryId = $this->extractDeliveryId($provider, $request);

        $normalizer = $this->normalizers->resolve($provider);
        $normalized = $normalizer->normalize($payload, $providerEvent);
        $dedupKey = $deliveryId ?? $normalizer->extractDedupKey($payload, $this->flatHeaders($request));

        $event = $this->dispatcher->dispatch($trigger, $normalized, $provider, $providerEvent, $dedupKey);

        if (! $event) {
            return $this->successResponse('Duplicate or skipped.', [], 200);
        }

        return $this->successResponse('Webhook received.', ['event_id' => $event->id], 202);
    }

    private function extractSignature(string $provider, Request $request): ?string
    {
        return match ($provider) {
            'github' => $request->header('X-Hub-Signature-256'),
            'stripe' => $request->header('Stripe-Signature'),
            'airtable' => $request->header('X-Airtable-Hmac-Sha256'),
            'slack' => $this->pipeSignature(
                $request->header('X-Slack-Request-Timestamp'),
                $request->header('X-Slack-Signature'),
            ),
            'discord' => $this->pipeSignature(
                $request->header('X-Signature-Timestamp'),
                $request->header('X-Signature-Ed25519'),
            ),
            default => null,
        };
    }

    private function pipeSignature(?string $timestamp, ?string $signature): ?string
    {
        if ($timestamp === null || $signature === null) {
            return null;
        }

        return "{$timestamp}|{$signature}";
    }

    private function extractProviderEvent(Trigger $trigger, string $provider, Request $request): string
    {
        return match ($provider) {
            'github' => $request->header('X-GitHub-Event', 'push'),
            'stripe' => (string) $request->json('type', 'event'),
            'slack' => (string) $request->json('event.type', $request->json('type', 'event')),
            'discord' => (string) $request->json('type', 'message'),
            default => $trigger->triggerType?->slug ?? 'webhook',
        };
    }

    private function extractDeliveryId(string $provider, Request $request): ?string
    {
        return match ($provider) {
            'github' => $request->header('X-GitHub-Delivery'),
            'stripe' => $request->json('id'),
            'slack' => $request->json('event_id'),
            default => null,
        };
    }

    /**
     * @return array<string, string|null>
     */
    private function flatHeaders(Request $request): array
    {
        return collect($request->headers->all())
            ->map(fn (array $values) => $values[0] ?? null)
            ->all();
    }

    /**
     * @return array<string, string|null>
     */
    private function safeHeaders(Request $request): array
    {
        return collect($request->headers->all())
            ->except(['authorization', 'cookie', 'x-webhook-signature'])
            ->map(fn (array $values) => $values[0] ?? null)
            ->all();
    }
}
