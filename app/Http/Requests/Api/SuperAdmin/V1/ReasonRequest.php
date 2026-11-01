<?php

namespace App\Http\Requests\Api\SuperAdmin\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A change that needs a written reason (kept in the audit log) but not a
 * fresh authenticator code: suspending, retrying, toggling a catalog item.
 */
class ReasonRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:'.config('super_admin_security.step_up.reason_min_length'), 'max:500'],
        ];
    }
}
