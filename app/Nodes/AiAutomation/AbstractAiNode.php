<?php

namespace App\Nodes\AiAutomation;

use App\Ai\Agents\Workspace\AdHocPromptAgent;
use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Ai\WorkspaceModels;
use InvalidArgumentException;
use RuntimeException;

/**
 * What every single-turn AI node shares: picking the model (the node's own
 * choice, else the workspace default), routing the call through BYOK and the
 * model catalog, reporting `usage` so the run is metered, and reading a JSON
 * reply back safely.
 *
 * A subclass supplies its instructions and prompt and shapes the output.
 */
abstract class AbstractAiNode implements DeclaresEffect, DeclaresOutputSchema, NodeContract
{
    /**
     * The longest text an AI node will send, so one runaway value cannot
     * become an enormous provider bill.
     */
    protected const int MAX_TEXT_CHARACTERS = 100000;

    public function __construct(
        private readonly ModelCatalogResolver $modelCatalog,
        private readonly ByokProviderRegistrar $byok,
        private readonly WorkspaceModels $workspaceModels,
    ) {}

    public function category(): string
    {
        return NodeCategory::AiAutomation->value;
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    /**
     * The model picker every AI node offers, plus the retired provider and
     * model overrides that older graphs still carry.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function modelFields(): array
    {
        return [
            'model_catalog_slug' => Field::dynamic('Model', 'ai.models', 'Leave empty to use the workspace default model.', allowCustom: false),
            'provider' => Field::hidden('Legacy provider override; use Model instead.'),
            'model' => Field::hidden('Legacy model override; use Model instead.'),
        ];
    }

    /**
     * One model call.
     *
     * @param  array<string, mixed>  $config
     * @param  array<int, mixed>  $attachments  files the model reads with the prompt
     * @return array{text: string, usage: array<string, mixed>}
     */
    protected function ask(Run $run, array $config, string $instructions, string $prompt, array $attachments = []): array
    {
        $agent = new AdHocPromptAgent($instructions);

        // No model picked and no legacy override: the workspace's default.
        $slug = filled($config['model_catalog_slug'] ?? null)
            ? $config['model_catalog_slug']
            : (filled($config['provider'] ?? null) || filled($config['model'] ?? null) ? null : $this->workspaceModels->default($run->workspace_id)?->slug);

        if ($slug !== null) {
            $provider = $this->modelCatalog->providerChain($slug);
            $model = null;
        } else {
            $provider = $config['provider'] ?? null;
            $model = $config['model'] ?? null;
        }

        [$provider, $model] = $this->byok->apply($provider, $model, $run->workspace_id, $run->triggered_by);

        $response = $agent->prompt($prompt, attachments: $attachments, provider: $provider, model: $model);

        return [
            'text' => $response->text,
            // `meta` carries the provider/model that actually served the
            // call, so `CreditMeter` can price it at its real $ cost.
            'usage' => [...$response->usage->toArray(), ...$response->meta->toArray()],
        ];
    }

    /**
     * One model call whose reply is a JSON object.
     *
     * @param  array<string, mixed>  $config
     * @param  array<int, mixed>  $attachments
     * @return array{data: array<string, mixed>, usage: array<string, mixed>}
     */
    protected function askJson(Run $run, array $config, string $instructions, string $prompt, array $attachments = []): array
    {
        $reply = $this->ask($run, $config, $instructions."\n\nReply with a single JSON object and nothing else: no prose before or after it and no code fences.", $prompt, $attachments);
        $data = self::decodeJson($reply['text']);

        if ($data === null) {
            throw new RuntimeException('The AI did not return the structured answer this step needs. Run it again, or pick a different model.');
        }

        return ['data' => $data, 'usage' => $reply['usage']];
    }

    /**
     * A JSON object out of a model's reply, tolerating the code fence or the
     * sentence of preamble models add despite being told not to.
     *
     * @return array<string, mixed>|null
     */
    public static function decodeJson(string $reply): ?array
    {
        $reply = trim($reply);
        $reply = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $reply));

        $decoded = json_decode($reply, true);

        if (! is_array($decoded)) {
            $start = strpos($reply, '{');
            $end = strrpos($reply, '}');

            $decoded = $start !== false && $end !== false && $end > $start
                ? json_decode(substr($reply, $start, $end - $start + 1), true)
                : null;
        }

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : null;
    }

    /**
     * A required text value: stringified, trimmed, non-empty and within the
     * size limit.
     *
     * @param  array<string, mixed>  $config
     */
    protected function requiredText(array $config, string $key, string $label): string
    {
        $value = $config[$key] ?? null;
        $text = trim(is_scalar($value) ? (string) $value : (string) json_encode($value));

        if ($text === '') {
            throw new InvalidArgumentException("{$label} is empty, so there is nothing to send to the AI.");
        }

        if (mb_strlen($text) > static::MAX_TEXT_CHARACTERS) {
            throw new InvalidArgumentException("{$label} is too long (".number_format(mb_strlen($text)).' characters; the limit is '.number_format(static::MAX_TEXT_CHARACTERS).'). Shorten it or split it up.');
        }

        return $text;
    }

    /**
     * A list setting as clean strings: trimmed, blanks removed, duplicates
     * dropped.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function cleanList(array $config, string $key): array
    {
        $items = is_array($config[$key] ?? null) ? $config[$key] : preg_split('/[\r\n,]+/', (string) ($config[$key] ?? ''));

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $item): string => trim((string) $item), $items ?: []),
            fn (string $item): bool => $item !== '',
        )));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function usageSchema(): array
    {
        return ['title' => 'Usage', 'description' => 'Tokens used and the model that answered.'];
    }
}
