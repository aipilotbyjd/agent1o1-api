<?php

namespace App\Http\Requests\Api\SuperAdmin\V1;

use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Auth\SuperAdminTwoFactorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A sensitive write: it needs a written reason (kept in the audit log) and a
 * fresh authenticator code, so a stolen session alone cannot do it. Extend
 * this and merge in the endpoint's own rules.
 */
class StepUpRequest extends FormRequest
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
            'totp_code' => ['required', 'string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->requireFreshAuthenticatorCode($validator)];
    }

    /**
     * Adds an error unless the request carries a valid authenticator code for
     * the signed-in super admin. Skipped while other fields are invalid, so a
     * typo elsewhere does not use up a code.
     */
    protected function requireFreshAuthenticatorCode(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $admin = $this->user('super-admin');

        if (! $admin instanceof SuperAdmin
            || ! app(SuperAdminTwoFactorService::class)->verifyTotp($admin, (string) $this->input('totp_code'))) {
            $validator->errors()->add('totp_code', 'The two-factor code is invalid.');
        }
    }
}
