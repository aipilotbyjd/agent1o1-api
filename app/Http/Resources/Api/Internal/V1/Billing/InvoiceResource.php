<?php

namespace App\Http\Resources\Api\Internal\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Cashier\Invoice;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * Money is returned twice over: `*_raw` in the currency's minor unit for
     * arithmetic and sorting, and the `*` fields pre-formatted by Cashier for
     * display. A client that formats its own money should read the raw pair
     * and ignore the rest, rather than parsing the formatted string back.
     *
     * `hosted_invoice_url` and `invoice_pdf` are Stripe's own: rendering the
     * PDF locally would mean adding `dompdf/dompdf` for a document Stripe
     * already generates, brands, and keeps correct.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'currency' => $this->currency,

            'total' => $this->total(),
            'total_raw' => $this->rawTotal(),
            'subtotal' => $this->subtotal(),
            'tax' => $this->tax(),
            'amount_due' => $this->amountDue(),
            'amount_due_raw' => $this->rawAmountDue(),

            'date' => $this->date()->toIso8601String(),
            'due_date' => $this->dueDate()?->toIso8601String(),

            'hosted_invoice_url' => $this->hosted_invoice_url,
            'invoice_pdf' => $this->invoice_pdf,
        ];
    }
}
