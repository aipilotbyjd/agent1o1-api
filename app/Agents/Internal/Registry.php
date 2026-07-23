<?php

namespace App\Agents\Internal;

use InvalidArgumentException;

/**
 * The single source of truth for platform-owned agents. Every internal agent
 * is registered here under a stable name used for config overrides
 * (config/agents.php `internal.overrides`), run recording, and the admin
 * metadata endpoint.
 */
class Registry
{
    /**
     * @var array<string, class-string<InternalAgent>>
     */
    public const MAP = [
        // Reasoning
        'planner' => Reasoning\PlannerAgent::class,
        'reflection' => Reasoning\ReflectionAgent::class,
        'error_diagnosis' => Reasoning\ErrorDiagnosisAgent::class,
        // Memory
        'memory_extraction' => Memory\MemoryExtractionAgent::class,
        // Safety
        'moderation' => Safety\ModerationAgent::class,
        // Evaluation
        'eval_judge' => Evaluation\EvalJudgeAgent::class,
        // Workflow builder
        'workflow_builder' => Workflow\WorkflowBuilderAgent::class,
        'workflow_description' => Workflow\WorkflowDescriptionAgent::class,
        'workflow_enhancement' => Workflow\WorkflowEnhancementAgent::class,
        'workflow_naming' => Workflow\WorkflowNamingAgent::class,
        'workflow_refinement' => Workflow\WorkflowRefinementAgent::class,
        'node_configuration' => Workflow\NodeConfigurationAgent::class,
        'node_suggestion' => Workflow\NodeSuggestionAgent::class,
        // Utility
        'chat' => Utility\ChatAgent::class,
        'vision' => Utility\VisionAgent::class,
        'sentiment' => Utility\SentimentAgent::class,
        'summarizer' => Utility\SummarizerAgent::class,
        'text_classifier' => Utility\TextClassifierAgent::class,
        'structured_extract' => Utility\StructuredExtractAgent::class,
        'skill_generator' => Utility\SkillGeneratorAgent::class,
    ];

    public static function get(string $name): InternalAgent
    {
        $class = self::MAP[$name] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Unknown internal agent \"{$name}\".");
        }

        return app($class);
    }

    public static function nameOf(string $class): string
    {
        $name = array_search($class, self::MAP, true);

        return $name === false ? $class : $name;
    }

    /**
     * @return array<string, class-string<InternalAgent>>
     */
    public static function all(): array
    {
        return self::MAP;
    }
}
