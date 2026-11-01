<?php

namespace App\Http\Requests\Api\Internal\V1\Triggers;

use App\Enums\Agents\AutonomyMode;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Triggers\TriggerType;
use App\Enums\Triggers\WebhookAuthentication;
use App\Models\Workspaces\Workspace;
use App\Rules\SafeOutboundUrl;
use App\Rules\Timezone;
use App\Rules\TriggerReader;
use Closure;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTriggerRequest extends FormRequest
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
            'target_type' => ['required', Rule::enum(TriggerTargetType::class)],
            // Scoped to the route's workspace: without this a member could
            // point a trigger (and therefore a real run, now that
            // `TargetRunStarter` starts one) at another tenant's workflow.
            // `TargetRunStarter` re-checks the same invariant at fire time,
            // since a target can be moved or deleted after creation.
            'target_id' => [
                'required',
                'uuid',
                Rule::exists($this->targetModel(), 'id')
                    ->where('workspace_id', $this->workspace()?->id)
                    ->whereNull('deleted_at'),
            ],
            'type' => ['required', Rule::enum(TriggerType::class)],
            'preset_id' => ['nullable', 'uuid', 'exists:trigger_presets,id'],
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
            // A polling trigger's endpoint: fetched by the server, so it must be a public URL.
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
                // One the creator could pick themselves: a team account or
                // their own personal one, as on the canvas (TriggerNodeSync).
                Rule::exists('connector_credentials', 'id')
                    ->where('workspace_id', $this->workspace()?->id)
                    ->whereNull('deleted_at')
                    ->where(fn ($credentials) => $credentials
                        ->where('scope', ConnectorCredentialScope::Team->value)
                        ->orWhere('created_by', $this->user()?->id)),
            ],
            'signing_secret' => ['nullable', 'string'],
        ];
    }

    /**
     * The model the `target_id` must exist in: the same class the morph map
     * aliases `target_type` to.
     *
     * @return class-string<Model>
     */
    private function targetModel(): string
    {
        $type = TriggerTargetType::tryFrom((string) $this->input('target_type'));

        // An unknown target_type already fails its own enum rule; falling back
        // to Workflow just keeps this rule from erroring before that message
        // is produced.
        return ($type ?? TriggerTargetType::Workflow)->modelClass();
    }

    private function workspace(): ?Workspace
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace ? $workspace : null;
    }
}
