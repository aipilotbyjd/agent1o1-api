<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Billing\StripeWebhookFailure;
use App\Services\SuperAdmin\Billing\SuperAdminWebhookService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuperAdminWebhookController extends Controller
{
    public function __construct(private readonly SuperAdminWebhookService $webhooks) {}

    /**
     * Stripe events our handler failed on, open ones first.
     */
    public function failures(Request $request)
    {
        $request->validate(['status' => ['nullable', Rule::in(['open', 'resolved'])]]);

        $failures = StripeWebhookFailure::query()
            ->when($request->string('status')->toString() === 'open', fn ($query) => $query->unresolved())
            ->when($request->string('status')->toString() === 'resolved', fn ($query) => $query->whereNotNull('resolved_at'))
            ->orderByRaw('resolved_at is null desc')
            ->orderByDesc('last_failed_at')
            ->orderByDesc('id')
            ->limit(min($request->integer('limit', 50), 200))
            ->get();

        return ApiResponse::success($failures->map(fn (StripeWebhookFailure $failure): array => [
            'id' => $failure->id,
            'event_id' => $failure->stripe_event_id,
            'type' => $failure->type,
            'error' => $failure->error,
            'attempts' => $failure->attempts,
            'first_failed_at' => $failure->first_failed_at,
            'last_failed_at' => $failure->last_failed_at,
            'resolved_at' => $failure->resolved_at,
        ])->all());
    }

    public function replay(ReasonRequest $request, string $eventId)
    {
        $result = $this->webhooks->replay($request->user('super-admin'), $eventId, $request->validated('reason'));

        return ApiResponse::success($result, 'Event replayed.');
    }
}
