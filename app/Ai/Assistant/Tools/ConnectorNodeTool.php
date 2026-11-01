<?php

namespace App\Ai\Assistant\Tools;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\Assistant;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * One integration node (Gmail, Slack, Calendar, …) as an assistant tool,
 * always acting through the owner's own connected account.
 *
 * The credential is pinned when the tool is built (`ConnectorToolProvider`)
 * and the model never sees a field for it: `credential_id`/`access_token`
 * are stripped from the schema and overwritten on every call, so a tool
 * call can't reach another account.
 */
class ConnectorNodeTool extends AssistantTool
{
    /**
     * Config keys the model may never set.
     */
    private const array PROTECTED_KEYS = ['credential_id', 'access_token'];

    /**
     * Characters of a node's output handed back to the model.
     */
    private const int MAX_RESULT_CHARS = 20000;

    public function __construct(
        private readonly NodeContract $node,
        private readonly Assistant $assistant,
        private readonly ConnectorCredential $credential,
    ) {}

    public function name(): string
    {
        return $this->node->type();
    }

    public function description(): Stringable|string
    {
        return "{$this->node->name()}: {$this->node->description()} (uses {$this->credential->name})";
    }

    public function effect(): AssistantToolEffect
    {
        if (! $this->node instanceof DeclaresEffect) {
            return AssistantToolEffect::Write;
        }

        return AssistantToolEffect::from($this->node->effect([])->value);
    }

    public function category(): string
    {
        return $this->node->category();
    }

    public function approvalReason(Request $request): string
    {
        return "{$this->node->name()} using {$this->credential->name}";
    }

    protected function execute(Request $request): string
    {
        $output = $this->node->execute($this->run(), $this->config($request->all()), ['input' => [], 'nodes' => []]);

        $json = json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        return mb_strlen($json) > self::MAX_RESULT_CHARS
            ? mb_substr($json, 0, self::MAX_RESULT_CHARS).'… [output cut short]'
            : $json;
    }

    /**
     * Only the node's own fields, minus credentials, then the pinned
     * credential on top, so nothing the model sends can replace it.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function config(array $arguments): array
    {
        $allowed = array_diff(array_keys($this->node->configSchema()['properties'] ?? []), self::PROTECTED_KEYS);

        return [...array_intersect_key($arguments, array_flip($allowed)), 'credential_id' => $this->credential->id];
    }

    /**
     * Nodes are written for workflow runs; they only read the run's
     * workspace and who started it (for credential lookup). An unsaved run
     * carries both without adding an entry to the workspace's run history.
     */
    private function run(): Run
    {
        $run = new Run;
        $run->forceFill(['workspace_id' => $this->assistant->workspace_id, 'triggered_by' => $this->assistant->user_id]);
        $run->setRelation('workspace', $this->assistant->workspace);

        return $run;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $configSchema = $this->node->configSchema();
        $required = $configSchema['required'] ?? [];
        $result = [];

        foreach ($configSchema['properties'] ?? [] as $key => $property) {
            if (in_array($key, self::PROTECTED_KEYS, true)) {
                continue;
            }

            $type = $this->toType($property, $schema);
            $result[$key] = in_array($key, $required, true) ? $type->required() : $type;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $property
     */
    private function toType(array $property, JsonSchema $schema): Type
    {
        $type = match ($property['type'] ?? null) {
            'object' => $schema->object(collect($property['properties'] ?? [])
                ->map(function (array $nested, string $key) use ($property, $schema) {
                    $type = $this->toType($nested, $schema);

                    return in_array($key, $property['required'] ?? [], true) ? $type->required() : $type;
                })
                ->all()),
            'array' => $schema->array()->items(isset($property['items']) ? $this->toType($property['items'], $schema) : $schema->string()),
            'integer' => $schema->integer(),
            'number' => $schema->number(),
            'boolean' => $schema->boolean(),
            default => $schema->string(),
        };

        if (isset($property['enum']) && method_exists($type, 'enum')) {
            $type = $type->enum($property['enum']);
        }

        if (isset($property['description']) && method_exists($type, 'description')) {
            $type = $type->description($property['description']);
        }

        return $type;
    }
}
