<?php

namespace App\Services;

use App\Engine\Webhook\WebhookRegistry;
use App\Models\Trigger;
use App\Models\TriggerType;
use App\Models\Workflow;
use Cron\CronExpression;
use InvalidArgumentException;

class TriggerService
{
    public function __construct(
        private readonly WebhookRegistry $webhookRegistry,
        private readonly TriggerValidationService $validation,
        private readonly TriggerRegistrationService $registration,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Workflow $workflow, array $data): Trigger
    {
        $triggerType = isset($data['trigger_type_id'])
            ? TriggerType::with('category', 'fields')->findOrFail($data['trigger_type_id'])
            : null;

        $fieldValues = $data['field_values'] ?? [];

        if ($triggerType) {
            if ($triggerType->requires_credential && empty($data['credential_id'])) {
                throw new InvalidArgumentException('This trigger requires a credential.');
            }

            $this->validation->validateFieldValues($triggerType, $fieldValues);
        }

        $type = $this->resolveType($triggerType, $data);
        $provider = $this->resolveProvider($triggerType);

        $settings = $data['settings'] ?? [];

        if (! empty($fieldValues)) {
            $settings['field_values'] = $fieldValues;
        }

        if ($provider !== null && $triggerType?->isPollingBased()) {
            $settings['polling_provider'] = $provider;
        }

        $interval = $data['polling_interval_seconds'] ?? null;

        $trigger = Trigger::create([
            'workflow_id' => $workflow->id,
            'workspace_id' => $workflow->workspace_id,
            'credential_id' => $data['credential_id'] ?? null,
            'trigger_type_id' => $triggerType?->id,
            'name' => $data['name'],
            'type' => $type,
            'is_active' => true,
            'webhook_provider' => $provider,
            'polling_interval_seconds' => $interval,
            'polling_next_check_at' => $type === 'polling' ? now() : null,
            'schedule_expression' => $data['schedule_expression'] ?? null,
            'schedule_timezone' => $data['schedule_timezone'] ?? 'UTC',
            'settings' => $settings ?: null,
        ]);

        if ($trigger->isWebhook()) {
            $this->webhookRegistry->provision($trigger);
        }

        if ($trigger->isScheduled() && $trigger->schedule_expression) {
            $this->setSchedule($trigger, $trigger->schedule_expression, $trigger->schedule_timezone);
        }

        // App-specific webhook: attempt provider registration when a credential is present.
        if ($trigger->isAppSpecific() && $triggerType?->isWebhookBased() && $trigger->credential_id) {
            $trigger->setRelation('triggerType', $triggerType);

            try {
                $this->registration->registerWebhookTrigger($trigger);
            } catch (\Throwable) {
                // Failure is recorded on webhook_status; keep the trigger for retry/fix.
            }
        }

        return $trigger->fresh();
    }

    public function pause(Trigger $trigger): Trigger
    {
        $trigger->update(['is_paused' => true]);

        return $trigger->fresh();
    }

    public function resume(Trigger $trigger): Trigger
    {
        $trigger->update(['is_paused' => false]);

        return $trigger->fresh();
    }

    public function delete(Trigger $trigger): void
    {
        if ($trigger->isAppSpecific()) {
            $this->registration->unregisterWebhookTrigger($trigger);
        }

        $trigger->delete();
    }

    public function setPollingInterval(Trigger $trigger, int $seconds): Trigger
    {
        $trigger->update([
            'polling_interval_seconds' => max(30, $seconds),
            'polling_next_check_at' => now()->addSeconds(max(30, $seconds)),
        ]);

        return $trigger->fresh();
    }

    public function setSchedule(Trigger $trigger, string $expression, string $timezone = 'UTC'): Trigger
    {
        if (! CronExpression::isValidExpression($expression)) {
            throw new InvalidArgumentException("Invalid cron expression: {$expression}");
        }

        $cron = new CronExpression($expression);

        $trigger->update([
            'schedule_expression' => $expression,
            'schedule_timezone' => $timezone,
            'schedule_next_run_at' => $cron->getNextRunDate('now', 0, false, $timezone),
        ]);

        return $trigger->fresh();
    }

    /**
     * Resolve the generic trigger type column from a catalog type (or raw input).
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveType(?TriggerType $triggerType, array $data): string
    {
        if (! $triggerType) {
            return $data['type'];
        }

        if ($triggerType->category?->category_type === 'schedule') {
            return 'scheduled';
        }

        return match ($triggerType->execution_mode) {
            'webhook' => 'webhook',
            'polling' => 'polling',
            default => 'manual',
        };
    }

    private function resolveProvider(?TriggerType $triggerType): ?string
    {
        if ($triggerType?->category?->category_type === 'app_specific') {
            return $triggerType->category->slug;
        }

        return null;
    }
}
