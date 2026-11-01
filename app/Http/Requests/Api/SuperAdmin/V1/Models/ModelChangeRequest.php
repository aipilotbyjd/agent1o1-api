<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Models;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Models\Ai\ModelCatalog;
use Illuminate\Validation\Validator;

/**
 * A change to a model that needs a written reason, and, when the model is one
 * of the internal ones the platform's own features are wired to (the workflow
 * builder, the personal assistant), a fresh authenticator code as well: a
 * wrong move there breaks a feature for every customer.
 */
class ModelChangeRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'totp_code' => [$this->touchesInternalModel() ? 'required' : 'nullable', 'string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->touchesInternalModel()) {
                    $this->requireFreshAuthenticatorCode($validator);
                }
            },
        ];
    }

    protected function touchesInternalModel(): bool
    {
        $model = $this->route('model');

        return $model instanceof ModelCatalog && $model->is_internal;
    }
}
