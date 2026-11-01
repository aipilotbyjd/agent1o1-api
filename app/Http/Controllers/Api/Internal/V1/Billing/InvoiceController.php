<?php

namespace App\Http\Controllers\Api\Internal\V1\Billing;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Billing\InvoiceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;
use Laravel\Cashier\Exceptions\InvalidInvoice;

/**
 * The workspace's billing history: what Stripe actually charged, as opposed
 * to `CreditController`'s ledger, which is what the workspace consumed.
 *
 * Every read here is a live Stripe call rather than a local mirror: invoices
 * are Stripe's record, and a copy in our database would only be a second
 * source of truth to keep in sync. The cost is that these endpoints fail when
 * Stripe does, which is the right trade for a settings screen.
 *
 * `Cashier::useCustomerModel(Workspace::class)` makes the workspace the Stripe
 * customer, so all of this hangs off `$workspace` directly.
 */
class InvoiceController extends Controller
{
    /**
     * Past invoices, newest first, including still-open ones: an unpaid
     * invoice is precisely what a customer needs to see when a charge has
     * failed, so this deliberately doesn't filter to paid only.
     *
     * Cursor-paginated because Stripe's list API is: it has no total count
     * and no offset, so the client walks `next_cursor` rather than asking for
     * page 7. A workspace that never reached checkout has no Stripe customer,
     * and Cashier returns an empty collection for it rather than erroring:
     * the common case for a Free workspace opening the billing screen.
     */
    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::BillingView);

        $invoices = $workspace->cursorPaginateInvoices(
            perPage: min((int) $request->integer('per_page', 24), 100),
            cursor: $request->query('cursor'),
        );

        return ApiResponse::cursorPaginated(InvoiceResource::collection($invoices));
    }

    /**
     * A preview of the next charge, so the customer can see what is coming
     * before it lands. Null whenever there is nothing to preview: no Stripe
     * customer, or no active subscription to project forward.
     */
    public function upcoming(Workspace $workspace)
    {
        $this->requirePermission(Permission::BillingView);

        $invoice = $workspace->upcomingInvoice();

        return ApiResponse::success([
            'invoice' => $invoice ? InvoiceResource::make($invoice) : null,
        ]);
    }

    /**
     * A single invoice.
     *
     * An id belonging to another workspace is answered with 404, not 403:
     * Cashier's `Invoice` constructor rejects a customer mismatch, and
     * collapsing that into "not found" keeps the endpoint from confirming
     * that someone else's invoice id exists. This matches
     * `Controller::ensureBelongsToWorkspace()`, which aborts 404 for the same
     * reason on every other resource.
     */
    public function show(Workspace $workspace, string $invoiceId)
    {
        $this->requirePermission(Permission::BillingView);

        try {
            $invoice = $workspace->findInvoice($invoiceId);
        } catch (InvalidInvoice) {
            $invoice = null;
        }

        // Returned directly rather than via `abort(404)`, whose global handler
        // renders "Endpoint not found": true of a bad route, misleading here.
        if ($invoice === null) {
            return ApiResponse::notFound('Invoice not found.');
        }

        return ApiResponse::success(['invoice' => InvoiceResource::make($invoice)]);
    }
}
