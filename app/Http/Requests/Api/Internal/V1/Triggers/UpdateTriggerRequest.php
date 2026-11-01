<?php

namespace App\Http\Requests\Api\Internal\V1\Triggers;

use App\Enums\Agents\AutonomyMode;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Triggers\WebhookAuthentication;
use App\Models\Triggers\Trigger;
use App\Rules\SafeOutboundUrl;
use App\Rules\Timezone;
use App\Rules\TriggerReader;
use Closure;
use Cron\CronExpression;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTriggerRequest extends FormRequest
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
            'config' => ['nullable', 'array'],
            // For an agent target: the mode its triggered turns run under, overriding the agent's own.
            'config.autonomy_mode' => ['nullable', Rule::enum(AutonomyMode::class)],
            'config.test_mode' => ['nullable', 'boolean'],
            // A schedule trigger's cron expression: the scheduler parses it every
            // minute, so an invalid one must never get stored.
            'config.cron' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! CronExpression::isValidExpression((string) $value)) {
                    $fail('The cron expression is not valid.');
                }
            }],
            // A one-time schedule's date and time.
            'config.run_once_at' => ['nullable', 'date'],
            'config.url' => ['nullable', 'string', new SafeOutboundUrl],
            // For an agent target: what it's told, templated against the payload.
            // Every config key needs a rule here: validated() drops the rest.
            'config.message' => ['nullable', 'string', 'max:10000'],
            // A URL polling trigger: how the response is read into items.
            'config.method' => ['nullable', 'string', Rule::in(['get', 'post', 'GET', 'POST'])],
            'config.newest_first' => ['nullable', 'boolean'],
            'config.items_path' => ['nullable', 'string', 'max:255'],
            'config.id_path' => ['nullable', 'string', 'max:255'],
            'config.cursor_path' => ['nullable', 'string', 'max:255'],
            'config.cursor_param' => ['nullable', 'string', 'max:255'],
            'config.filters' => ['nullable', 'array'],
            // A polling trigger that runs an app reader instead of fetching a URL.
            'config.node_type' => ['nullable', 'string', new TriggerReader],
            'config.node_config' => ['nullable', 'array'],
            'config.poll_interval_minutes' => ['nullable', 'integer', 'between:'.max(1, (int) config('triggers.min_poll_interval_minutes')).',1440'],
            'config.timezone' => ['nullable', 'string', new Timezone],
            'config.authentication' => ['nullable', Rule::enum(WebhookAuthentication::class)],
            // An HTTP header name: letters, digits and dashes.
            'config.header_name' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'config.if_already_running' => ['nullable', 'string', Rule::in(['queue', 'skip'])],
            'config.start_from' => ['nullable', 'string', Rule::in(['now', 'beginning'])],
            'config.max_runs_per_minute' => ['nullable', 'integer', 'between:1,600'],
            'config.batch_seconds' => ['nullable', 'integer', 'between:5,3600'],
            'config.debounce_seconds' => ['nullable', 'integer', 'between:5,3600'],
            'config.storm_limit' => ['nullable', 'integer', 'between:10,10000'],
            'config.max_concurrent_runs' => ['nullable', 'integer', 'between:1,100'],
            'config.response_mode' => ['nullable', 'string', Rule::in(['immediate', 'wait'])],
            'config.response_timeout_seconds' => ['nullable', 'integer', 'between:1,'.config('triggers.max_sync_wait_seconds')],
            'config.pass_headers' => ['nullable', 'array', 'max:20'],
            'config.pass_headers.*' => ['string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'config.max_failures' => ['nullable', 'integer', 'between:'.implode(',', config('triggers.max_failures_range'))],
            'is_active' => ['nullable', 'boolean'],
            'credential_id' => [
                'nullable',
                'uuid',
                Rule::exists('connector_credentials', 'id')
                    ->where('workspace_id', $this->trigger()?->workspace_id)
                    ->whereNull('deleted_at')
                    ->where(fn ($credentials) => $credentials
                        ->where('scope', ConnectorCredentialScope::Team->value)
                        ->orWhere('created_by', $this->user()?->id)),
            ],
            // Empty string clears it: the webhook then accepts unsigned requests again.
            'signing_secret' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * A trigger that belongs to a canvas node is configured on the canvas:
     * the next publish would overwrite an edit made here (`TriggerNodeSync`).
     *
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $trigger = $this->trigger();

                if ($trigger !== null && $trigger->trigger_node_key !== null && ($this->has('config') || $this->has('credential_id'))) {
                    $validator->errors()->add('config', 'This trigger is part of the workflow. Edit it on the canvas, then publish.');
                }
            },
        ];
    }

    private function trigger(): ?Trigger
    {
        $trigger = $this->route('trigger');

        return $trigger instanceof Trigger ? $trigger : null;
    }
}
