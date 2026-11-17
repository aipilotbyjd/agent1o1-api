<?php

namespace App\Http\Requests\Api\Internal\V1\Billing;

use App\Enums\Billing\CancellationOffer;
use App\Enums\Billing\CancellationReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AcceptCancellationOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'offer' => ['required', new Enum(CancellationOffer::class)],
            'reason' => ['required', new Enum(CancellationReason::class)],
            'comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
