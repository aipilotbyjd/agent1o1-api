<?php

namespace App\Http\Requests\Api\Internal\V1\Ai;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Services\Ai\AiProviderSettings;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreAiProviderCredentialRequest extends FormRequest
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
            'execution_provider' => ['required', 'string', Rule::in(array_keys((array) config('byok.providers'))), $this->providerIsSwitchedOn(...)],
            'api_key' => ['required', 'string', 'min:8', 'max:500'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'scope' => ['sometimes', new Enum(ConnectorCredentialScope::class)],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A provider a super admin has switched off takes no new keys.
     */
    private function providerIsSwitchedOn(string $attribute, mixed $value, Closure $fail): void
    {
        $settings = app(AiProviderSettings::class);

        if (is_string($value) && $settings->isSupported($value) && ! $settings->byokEnabled($value)) {
            $fail($settings->disabledMessage($value));
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'execution_provider.in' => 'Keys for this provider aren\'t supported.',
        ];
    }
}
