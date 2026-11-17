<?php

namespace App\Http\Controllers\Api\Internal\V1\Billing;

use App\Actions\Billing\AcceptCancellationOfferAction;
use App\Enums\Billing\CancellationOffer;
use App\Enums\Billing\CancellationReason;
use App\Enums\Workspaces\AuditAction;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Billing\AcceptCancellationOfferRequest;
use App\Http\Resources\Api\Internal\V1\Billing\SubscriptionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CancellationOffers;
use App\Services\Workspaces\AuditLogger;

/**
 * The cancellation flow around `SubscriptionController::cancel()`: what to
 * ask, what to offer for each answer, and taking an offer instead of leaving.
 */
class SubscriptionCancellationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Everything the cancellation screens need in one read: the reasons to
     * choose from, the one offer (if any) that goes with each, and the date
     * the plan would end on.
     */
    public function show(Workspace $workspace, CancellationOffers $offers)
    {
        $this->requirePermission(Permission::BillingManage);

        $subscription = $workspace->subscription('default');

        abort_if($subscription === null, 404, 'Workspace has no active subscription to cancel.');

        $reasons = array_map(function (CancellationReason $reason) use ($workspace, $offers): array {
            $offer = $offers->offerFor($workspace, $reason);

            return [
                'value' => $reason->value,
                'label' => $reason->label(),
                'comment_prompt' => $reason->commentPrompt(),
                'offer' => $offer === null ? null : [
                    'type' => $offer['type']->value,
                    'percent_off' => $offer['percent_off'],
                    'months' => $offer['months'],
                ],
            ];
        }, CancellationReason::cases());

        return ApiResponse::success([
            'ends_at' => $subscription->current_period_ends_at,
            'reasons' => $reasons,
        ]);
    }

    public function accept(AcceptCancellationOfferRequest $request, Workspace $workspace, AcceptCancellationOfferAction $accept)
    {
        $this->requirePermission(Permission::BillingManage);

        $offer = CancellationOffer::from($request->validated('offer'));

        $record = $accept->execute(
            $workspace,
            $request->user(),
            $offer,
            CancellationReason::from($request->validated('reason')),
            $request->validated('comment'),
        );

        $this->audit->record($workspace->id, AuditAction::SubscriptionCancellationOfferAccepted, metadata: [
            'offer' => $offer->value,
            'reason' => $record->reason?->value,
        ]);

        return ApiResponse::success([
            'outcome' => $record->outcome->value,
            'percent_off' => $record->discount_percent,
            'months' => $record->discount_months,
            'subscription' => SubscriptionResource::make($workspace->subscription('default')?->load('plan')),
        ], $offer === CancellationOffer::Discount ? 'Discount applied.' : 'Help requested.');
    }
}
