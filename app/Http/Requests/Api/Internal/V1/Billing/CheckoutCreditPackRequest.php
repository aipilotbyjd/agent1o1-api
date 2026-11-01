<?php

namespace App\Http\Requests\Api\Internal\V1\Billing;

use App\Services\Billing\PackCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\In;

class CheckoutCreditPackRequest extends FormRequest
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
            'pack_key' => ['required', 'string', new In(array_keys(app(PackCatalog::class)->sellable()))],
        ];
    }
}
