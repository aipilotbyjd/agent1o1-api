<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Alerts;

use App\Enums\Notifications\AlertSeverity;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Models\SuperAdmin\AlertRule;
use App\Services\SuperAdmin\Alerts\AlertRuleCatalog;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Tuning an alert rule. Only the thresholds the rule's evaluator understands
 * may be set, and only as numbers or booleans: a typo is refused instead of
 * silently doing nothing.
 */
class UpdateAlertRuleRequest extends ReasonRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'severity' => ['sometimes', Rule::enum(AlertSeverity::class)],
            'cooldown_minutes' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'enabled' => ['sometimes', 'boolean'],
            'config' => ['sometimes', 'array'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $rule = $this->route('rule');

            if (! $rule instanceof AlertRule || ! $this->has('config')) {
                return;
            }

            $allowed = app(AlertRuleCatalog::class)->tunableKeys($rule);

            foreach ((array) $this->input('config') as $key => $value) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add("config.{$key}", "[{$key}] is not a setting of this rule. Allowed: ".implode(', ', $allowed).'.');
                } elseif (! is_numeric($value) && ! is_bool($value)) {
                    $validator->errors()->add("config.{$key}", "[{$key}] must be a number or a boolean.");
                } elseif (is_numeric($value) && $value < 0) {
                    $validator->errors()->add("config.{$key}", "[{$key}] cannot be negative.");
                }
            }
        }];
    }
}
