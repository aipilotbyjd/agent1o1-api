<?php

namespace App\Http\Requests\Api\Internal\V1\User;

use App\Services\Auth\SignupAttribution;
use Illuminate\Foundation\Http\FormRequest;

class RecordSignupAttributionRequest extends FormRequest
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
        return SignupAttribution::rules();
    }
}
