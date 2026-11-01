<?php

namespace App\Console\Commands\Triggers;

use App\Enums\Triggers\TriggerNodeType;
use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Triggers\TriggerType;
use App\Enums\Triggers\WebhookAuthentication;
use App\Exceptions\WorkflowValidationException;
use App\Models\Triggers\Trigger;
use App\Models\Workflows\Workflow;
use App\Services\Triggers\TriggerNodes;
use App\Services\Workflows\Engine\GraphAdvancer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lists, converts or deletes legacy workflow triggers.
 *
 * A legacy trigger was made before trigger nodes existed, so it has no node
 * on the canvas (`trigger_node_key` is null) and the editor never shows it.
 *
 * Converting adds a matching trigger node to the workflow's draft, wired to
 * the steps a run currently starts at, and binds the existing row to it, so
 * a webhook keeps its URL and secret. The author then reviews and publishes.
 * A manual trigger has nothing to become (the Run button already does its
 * job), so it's deleted instead. One with a provider source is left alone.
 */
class LegacyTriggersCommand extends Command
{
    protected $signature = 'triggers:legacy
        {--convert : Add a trigger node for each one and bind it}
        {--delete : Delete them}
        {--workspace= : Only this workspace}';

    protected $description = 'List workflow triggers that have no node on the canvas, and convert or delete them. Lists only, unless --convert or --delete is given.';

    /**
     * List the legacy triggers, then convert or delete them if asked.
     */
    public function handle(TriggerNodes $triggerNodes): int
    {
        if ($this->option('convert') && $this->option('delete')) {
            $this->error('Pick one of --convert or --delete.');

            return self::FAILURE;
        }

        $legacy = Trigger::query()
            ->where('target_type', TriggerTargetType::Workflow->value)
            ->whereNull('trigger_node_key')
            ->when($this->option('workspace'), fn ($triggers, string $workspaceId) => $triggers->where('workspace_id', $workspaceId))
            ->with('target')
            ->orderBy('created_at')
            ->get();

        $this->table(
            ['Trigger', 'Workspace', 'Workflow', 'Type', 'Active', 'Last fired'],
            $legacy->map(fn (Trigger $trigger): array => [
                $trigger->id,
                $trigger->workspace_id,
                $trigger->target?->name ?? '(deleted)',
                $trigger->type->value,
                $trigger->is_active ? 'yes' : 'no',
                $trigger->last_run_at?->toDateTimeString() ?? 'never',
            ])->all(),
        );

        if ($legacy->isEmpty() || (! $this->option('convert') && ! $this->option('delete'))) {
            $this->info("{$legacy->count()} legacy trigger(s). Nothing changed.");

            return self::SUCCESS;
        }

        $converted = 0;
        $deleted = 0;

        foreach ($legacy as $trigger) {
            $workflow = $trigger->target;

            if ($this->option('delete') || $trigger->type === TriggerType::Manual || ! $workflow instanceof Workflow) {
                $trigger->delete();
                $deleted++;

                continue;
            }

            // The Webhook node has no provider signatures (GitHub, Stripe,
            // Slack): those belong to each app's own trigger. Leave it rather
            // than convert it into something weaker.
            if ($trigger->preset_id !== null) {
                $this->warn("Skipped {$trigger->id}: it checks {$trigger->preset?->name} signatures, which the Webhook node doesn't. Leave it, or delete it with --delete.");

                continue;
            }

            try {
                $this->convert($trigger, $workflow, $triggerNodes);
                $converted++;
            } catch (WorkflowValidationException $exception) {
                $this->warn("Skipped {$trigger->id}: ".implode(' ', $exception->errors()));
            }
        }

        $this->info("Converted {$converted} and deleted {$deleted} legacy trigger(s). Converted workflows need publishing for the change to go live.");

        return self::SUCCESS;
    }

    /**
     * Add a trigger node for a legacy trigger to its workflow's draft and
     * bind the trigger to it.
     *
     * @throws WorkflowValidationException when the draft can't be saved
     */
    private function convert(Trigger $trigger, Workflow $workflow, TriggerNodes $triggerNodes): void
    {
        $nodeType = match ($trigger->type) {
            TriggerType::Schedule => TriggerNodeType::Schedule,
            TriggerType::Polling => TriggerNodeType::Polling,
            default => TriggerNodeType::Webhook,
        };

        DB::transaction(function () use ($trigger, $workflow, $triggerNodes, $nodeType): void {
            $graph = $workflow->editorGraph();
            $key = 'trigger_'.Str::lower(Str::random(8));

            // Connected to every step a run starts at today, so the converted
            // trigger starts the same steps the old one did.
            $startKeys = array_values(array_filter(
                GraphAdvancer::entryKeys($graph),
                fn (string $entryKey): bool => ! $triggerNodes->isTriggerEntry(collect($graph['nodes'])->firstWhere('key', $entryKey)),
            ));

            $graph['nodes'][] = [
                'key' => $key,
                'type' => $nodeType->value,
                'config' => [
                    ...array_intersect_key((array) $trigger->config, array_flip(['cron', 'timezone', 'filters', 'url', 'method', 'items_path', 'id_path', 'poll_interval_minutes', 'cursor_param', 'cursor_path'])),
                    // A secret on a webhook with no source was always checked as
                    // the generic signature, so keep exactly that.
                    'authentication' => $trigger->type === TriggerType::Webhook
                        ? (filled($trigger->signing_secret) ? WebhookAuthentication::Signature->value : WebhookAuthentication::None->value)
                        : null,
                    'credential_id' => $trigger->credential_id,
                    'activate' => $trigger->is_active,
                ],
                'position' => ['x' => -320, 'y' => 0],
            ];

            foreach ($startKeys as $startKey) {
                $graph['edges'][] = ['from' => $key, 'to' => $startKey, 'condition' => null];
            }

            $graph['nodes'][array_key_last($graph['nodes'])]['config'] = array_filter(
                $graph['nodes'][array_key_last($graph['nodes'])]['config'],
                fn (mixed $value): bool => $value !== null,
            );

            $workflow->replaceGraph($graph);
            $trigger->forceFill(['trigger_node_key' => $key])->save();
        });
    }
}
