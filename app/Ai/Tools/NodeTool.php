<?php

namespace App\Ai\Tools;

use App\Actions\Billing\DeductCreditsAction;
use App\Ai\Tools\Concerns\GatesActions;
use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Agents\AgentToolBinding;
use App\Models\Runs\Run;
use App\Services\Agents\UntrustedContent;
use App\Services\Billing\CreditMeter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Wraps a `NodeContract` as a `Laravel\Ai` tool for an `Agent`: the
 * bound-config/exposed-fields security boundary from `AgentToolBinding`'s
 * docblock lives entirely in `effectiveArguments()`/`schema()` below. **This
 * must never regress**: a tool-call argument can never override a bound
 * config value, and the model is never even shown a schema field for one.
 * That holds for a reviewer's edit too: an approved call's edited
 * arguments go through the same filter as the model's own.
 */
class NodeTool implements Approvable, Tool
{
    use GatesActions;

    public function __construct(
        private readonly NodeContract $node,
        private readonly AgentToolBinding $binding,
        private readonly Run $run,
    ) {}

    /**
     * The node's own type string, unique per agent (`agent_node`'s
     * `unique(agent_id, node_type)`): safe to use as the tool name.
     */
    public function name(): string
    {
        return $this->node->type();
    }

    public function description(): Stringable|string
    {
        return "{$this->node->name()}: {$this->node->description()}";
    }

    /**
     * Only the fields `schema()` offers are taken from the model: anything
     * else it sends is dropped, so a field left out of `exposed_fields` can't
     * be set just by naming it. Bound config is spread last as well, so a
     * matching key always loses to the bound value: the model can never
     * choose a credential, channel, or any other field the workspace member
     * fixed at attach time.
     */
    public function handle(Request $request): Stringable|string
    {
        return $this->guarded($request, function (array $arguments): string {
            $output = $this->node->execute($this->run, $this->effectiveArguments($arguments), ['input' => [], 'nodes' => []]);

            $this->chargeForNode($output);

            return UntrustedContent::wrap('tool:'.$this->node->type(), json_encode($output) ?: '{}');
        });
    }

    public function binding(): AgentToolBinding
    {
        return $this->binding;
    }

    /**
     * @return array<string, mixed>
     */
    protected function actionArguments(Request $request): array
    {
        return array_intersect_key($request->all(), array_flip($this->modelSettableKeys()));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function effectiveArguments(array $arguments): array
    {
        $arguments = array_intersect_key($arguments, array_flip($this->modelSettableKeys()));

        return [...$arguments, ...($this->binding->config ?? [])];
    }

    /**
     * @param  array<string, mixed>  $effectiveArguments
     */
    protected function actionEffect(array $effectiveArguments): ActionEffect
    {
        return $this->node instanceof DeclaresEffect ? $this->node->effect($effectiveArguments) : ActionEffect::Write;
    }

    /**
     * Bills what the node itself cost: nothing for most integration nodes,
     * a surcharge for `run_code`/`agent`, and the tokens an AI node such as
     * `ask_ai` spent on its own model call, none of which reaches the
     * calling turn's `usage`. Charged here rather than with the turn because
     * a tool call runs in whatever context called the agent (a chat turn, an
     * eval case, an Agent node in a workflow), and each of those only bills
     * its own model call. With overdraft, like any charge for work that has
     * already run.
     *
     * @param  array<string, mixed>  $output
     */
    private function chargeForNode(array $output): void
    {
        $usage = is_array($output['usage'] ?? null) ? $output['usage'] : null;
        $credits = app(CreditMeter::class)->costForAgentToolNode($this->node->type(), $usage);

        if ($credits === 0) {
            return;
        }

        app(DeductCreditsAction::class)->execute(
            $this->run->workspace,
            CreditTransactionType::AgentToolNode,
            (string) Str::uuid(),
            $credits,
            "Agent tool '{$this->node->type()}'",
            allowOverdraft: true,
        );
    }

    /**
     * Only exposed, unbound fields are ever shown to the model: a bound
     * field (even if also listed in `exposed_fields` by mistake) never gets
     * a schema entry, so the model has no way to know it exists to try to
     * override it.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $configSchema = $this->node->configSchema();
        $properties = $configSchema['properties'] ?? [];
        $required = $configSchema['required'] ?? [];
        $settableKeys = $this->modelSettableKeys();

        $result = [];

        foreach ($properties as $key => $propertySchema) {
            if (! in_array($key, $settableKeys, true)) {
                continue;
            }

            $type = $this->propertyToType($propertySchema, $schema);

            $result[$key] = in_array($key, $required, true) ? $type->required() : $type;
        }

        return $result;
    }

    /**
     * The config keys the model may supply: exposed (every schema property
     * when `exposed_fields` is unset) and not bound.
     *
     * @return array<int, string>
     */
    private function modelSettableKeys(): array
    {
        $properties = array_keys($this->node->configSchema()['properties'] ?? []);
        $boundKeys = array_keys($this->binding->config ?? []);
        $exposedKeys = $this->binding->exposed_fields ?? $properties;

        return array_values(array_diff(array_intersect($properties, $exposedKeys), $boundKeys));
    }

    /**
     * @param  array<string, mixed>  $propertySchema
     */
    private function propertyToType(array $propertySchema, JsonSchema $schema): Type
    {
        $type = match ($propertySchema['type'] ?? null) {
            'object' => $schema->object(collect($propertySchema['properties'] ?? [])
                ->map(function (array $nested, string $key) use ($propertySchema, $schema) {
                    $type = $this->propertyToType($nested, $schema);

                    return in_array($key, $propertySchema['required'] ?? [], true) ? $type->required() : $type;
                })
                ->all()),
            'array' => $schema->array()->items(
                isset($propertySchema['items']) ? $this->propertyToType($propertySchema['items'], $schema) : $schema->string(),
            ),
            'integer' => $schema->integer(),
            'number' => $schema->number(),
            'boolean' => $schema->boolean(),
            default => $schema->string(),
        };

        if (isset($propertySchema['enum']) && method_exists($type, 'enum')) {
            $type = $type->enum($propertySchema['enum']);
        }

        $description = implode(': ', array_filter([$propertySchema['title'] ?? null, $propertySchema['description'] ?? null]));

        return $description === '' ? $type : $type->description($description);
    }
}
