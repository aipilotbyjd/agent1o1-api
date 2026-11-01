<?php

namespace App\Services\Assistant\Tools;

use App\Ai\Assistant\Tools\AssistantTool;
use App\Ai\Assistant\Tools\CancelFollowUpTool;
use App\Ai\Assistant\Tools\ChangeTriggerTool;
use App\Ai\Assistant\Tools\CreateTriggerTool;
use App\Ai\Assistant\Tools\ForgetTool;
use App\Ai\Assistant\Tools\ImportSkillsTool;
use App\Ai\Assistant\Tools\ListFollowUpsTool;
use App\Ai\Assistant\Tools\ListTriggersTool;
use App\Ai\Assistant\Tools\RememberTool;
use App\Ai\Assistant\Tools\TrackFollowUpTool;
use App\Ai\Assistant\Tools\UpdateStyleTool;
use App\Contracts\Assistant\ProvidesAssistantTools;
use App\Enums\Assistant\AssistantToolRule;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantTurn;
use App\Services\Agents\Skills\SkillSources;
use App\Services\Assistant\Personalization\StyleProfiles;
use App\Services\Assistant\Triggers\TriggerDefinitions;
use Illuminate\Contracts\Container\Container;
use Laravel\Ai\AiManager;
use Laravel\Ai\Contracts\Providers\SupportsWebFetch;
use Laravel\Ai\Contracts\Providers\SupportsWebSearch;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;
use Throwable;

/**
 * Every tool the assistant gets for one turn: its built-ins, plus whatever
 * the tagged `assistant.tools` providers offer (connectors, later the
 * sandbox). Each is put behind the turn's `ToolGate`; tools the owner set
 * to "deny" are never offered at all.
 */
class ToolCatalog
{
    public const string TAG = 'assistant.tools';

    public function __construct(
        private readonly Container $container,
        private readonly AiManager $ai,
        private readonly StyleProfiles $styles,
        private readonly TriggerDefinitions $triggers,
        private readonly SkillSources $skillSources,
    ) {}

    /**
     * @param  string|array<string, string>  $provider  the turn's provider (or failover chain)
     * @return list<object>
     */
    public function forTurn(AssistantTurn $turn, string|array $provider): array
    {
        $session = $turn->session;
        $gate = ToolGate::forTurn($turn);

        $tools = collect([...$this->builtIns($session), ...$this->provided($session)])
            ->unique(fn (AssistantTool $tool): string => $tool->name())
            ->reject(fn (AssistantTool $tool): bool => $gate->ruleFor($tool) === AssistantToolRule::Deny)
            ->map(fn (AssistantTool $tool): AssistantTool => $tool->gatedBy($gate))
            ->values()
            ->all();

        return [...$tools, ...$this->webTools($provider)];
    }

    /**
     * Every tool the owner can set a rule for: what the settings screen lists.
     *
     * @return list<AssistantTool>
     */
    public function available(Assistant $assistant): array
    {
        $session = new AssistantSession(['assistant_id' => $assistant->id]);
        $session->setRelation('assistant', $assistant);

        return collect([...$this->builtIns($session), ...$this->provided($session)])
            ->unique(fn (AssistantTool $tool): string => $tool->name())
            ->values()
            ->all();
    }

    /**
     * Incognito conversations leave nothing behind, so they can't save memories.
     *
     * @return list<AssistantTool>
     */
    private function builtIns(AssistantSession $session): array
    {
        $assistant = $session->assistant;

        if ($session->incognito) {
            return [];
        }

        return [
            new RememberTool($assistant, $session->exists ? $session : null),
            new ForgetTool($assistant),
            new UpdateStyleTool($assistant, $this->styles),
            new CreateTriggerTool($assistant, $this->triggers),
            new ListTriggersTool($assistant),
            new ChangeTriggerTool($assistant, $this->triggers),
            new ImportSkillsTool($assistant, $this->skillSources),
            new TrackFollowUpTool($assistant, $session->exists ? $session : null),
            new ListFollowUpsTool($assistant),
            new CancelFollowUpTool($assistant),
        ];
    }

    /**
     * @return list<AssistantTool>
     */
    private function provided(AssistantSession $session): array
    {
        return collect($this->container->tagged(self::TAG))
            ->flatMap(fn (ProvidesAssistantTools $provider): array => $provider->toolsFor($session->assistant, $session))
            ->all();
    }

    /**
     * Provider-native web search and fetch, offered only when every provider
     * in the chain supports them: an unsupported one would throw on the
     * tool and fail the turn once failover reached it.
     *
     * @param  string|array<string, string>  $provider
     * @return list<WebSearch|WebFetch>
     */
    private function webTools(string|array $provider): array
    {
        try {
            $providers = collect(is_array($provider) ? array_keys($provider) : [$provider])
                ->map(fn (string $name) => $this->ai->textProvider($name));
        } catch (Throwable) {
            return [];
        }

        return array_values(array_filter([
            $providers->every(fn ($instance): bool => $instance instanceof SupportsWebSearch) ? new WebSearch : null,
            $providers->every(fn ($instance): bool => $instance instanceof SupportsWebFetch) ? new WebFetch : null,
        ]));
    }
}
