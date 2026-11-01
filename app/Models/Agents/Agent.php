<?php

namespace App\Models\Agents;

use App\Enums\Agents\AutonomyMode;
use App\Models\Ai\ModelCatalog;
use App\Models\Artifacts\Artifact;
use App\Models\User;
use App\Models\Workflows\Folder;
use App\Models\Workflows\Tag;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A conversational, tool-driven assistant: the "Agent" layer distinct from
 * the Workflow engine (docs/PLAN.md's "Architecture Overview"). Named `Agent`
 * (not `AgentModel`) per project convention; registered in
 * `AppServiceProvider::configureMorphMap()` as `TriggerTargetType::Agent`.
 */
#[Fillable(['workspace_id', 'folder_id', 'name', 'slug', 'description', 'icon', 'color', 'instructions', 'provider', 'model', 'model_catalog_id', 'temperature', 'settings', 'allow_self_updates', 'allow_skill_editing', 'allow_self_clone', 'autonomy_mode', 'test_mode', 'allow_web_fetch', 'created_by'])]
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public const ICONS = [
        'bot', 'brain', 'sparkles', 'search', 'target', 'shield', 'rocket', 'layers', 'flame', 'sliders-horizontal',
        'users', 'database', 'calendar-days', 'file-text', 'mail', 'megaphone', 'chart-line', 'headphones', 'code',
    ];

    public const COLORS = ['purple', 'green', 'blue', 'teal', 'orange', 'red', 'rainbow'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'provider' => 'anthropic',
        'allow_self_updates' => false,
        'allow_skill_editing' => false,
        'allow_self_clone' => true,
        'autonomy_mode' => 'ask',
        'test_mode' => false,
        'allow_web_fetch' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'temperature' => 'decimal:2',
            'settings' => 'array',
            'allow_self_updates' => 'boolean',
            'allow_skill_editing' => 'boolean',
            'allow_self_clone' => 'boolean',
            'autonomy_mode' => AutonomyMode::class,
            'test_mode' => 'boolean',
            'allow_web_fetch' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_agent');
    }

    /**
     * The public model identity this agent runs against, when opted in:
     * see `Services\Ai\ModelCatalogResolver`. `null` means this agent still
     * runs on its plain `provider`/`model` columns directly.
     */
    public function modelCatalog(): BelongsTo
    {
        return $this->belongsTo(ModelCatalog::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AgentVersion::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AgentSession::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }

    /**
     * Built-in/custom nodes attached as tools: see `AgentToolBinding`'s
     * docblock for the security-boundary design. Not a `BelongsToMany`
     * (unlike `workflows()` below) since built-in nodes have no row of
     * their own to belong to.
     */
    public function toolBindings(): HasMany
    {
        return $this->hasMany(AgentToolBinding::class);
    }

    /**
     * Workflows attached as tools (`Ai/Tools/WorkflowTool`): a real
     * `BelongsToMany` since, unlike built-in nodes, a `Workflow` is a real
     * Eloquent row.
     */
    public function workflows(): BelongsToMany
    {
        return $this->belongsToMany(Workflow::class, 'agent_workflow')
            ->withPivot('approval_policy')
            ->withTimestamps();
    }

    /**
     * Other agents this one may hand work to through `InvokeAgentTool`.
     */
    public function subagents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'agent_subagents', 'agent_id', 'subagent_id')->withTimestamps();
    }

    /**
     * Reusable instruction snippets injected into the system prompt
     * alongside `instructions()`: see `Services\Agents\SkillInjector`.
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'agent_skill')->withTimestamps();
    }

    /**
     * `document_embeddings.collection`s explicitly attached to this agent,
     * each searched on demand or put in its prompt on every turn (`mode`):
     * the opt-in scoping `ToolRegistry` prefers over its workspace-wide
     * fallback, and what `SkillInjector` reads always-included knowledge from.
     */
    public function knowledgeCollections(): HasMany
    {
        return $this->hasMany(AgentKnowledgeCollection::class);
    }

    /**
     * The `document_embeddings.collection` this agent's own exported
     * artifacts are indexed under: see `StoreArtifactAction`. Always
     * implicitly searchable by this agent, unlike `knowledgeCollections()`
     * which must be attached explicitly.
     */
    public function artifactKnowledgeCollection(): string
    {
        return "artifacts:{$this->id}";
    }

    /**
     * Durable key/value facts read/written across sessions.
     */
    public function memories(): HasMany
    {
        return $this->hasMany(AgentMemory::class);
    }

    /**
     * The memories a conversation with `$userId` may see: that user's own
     * plus workspace-wide ones (user_id null): a memory tied to one user
     * must never reach another user's conversation.
     */
    public function memoriesVisibleTo(?string $userId): HasMany
    {
        return $this->memories()->where(fn ($query) => $query->whereNull('user_id')->when($userId, fn ($query) => $query->orWhere('user_id', $userId)));
    }

    /**
     * Saved test suites graded against this agent: see `EvalRunner`.
     */
    public function evalSuites(): HasMany
    {
        return $this->hasMany(AgentEvalSuite::class);
    }

    /**
     * Whether/how this agent periodically reviews its own past sessions:
     * see `Services\Agents\ReflectionAnalyzer`.
     */
    public function reflectionSettings(): HasOne
    {
        return $this->hasOne(ReflectionSettings::class);
    }

    public function reflectionRuns(): HasMany
    {
        return $this->hasMany(ReflectionRun::class);
    }

    /**
     * Whether/how this agent's live sessions are automatically graded after
     * each turn: see `Services\Agents\SessionEvaluator`.
     */
    public function evaluationSettings(): HasOne
    {
        return $this->hasOne(AgentEvaluationSettings::class);
    }

    public function sessionEvaluations(): HasMany
    {
        return $this->hasMany(AgentSessionEvaluation::class);
    }

    public function reflections(): HasMany
    {
        return $this->hasMany(Reflection::class);
    }

    /**
     * Every side-effecting tool call this agent made or tried to make:
     * see `Services\Agents\Approvals\ActionGate`.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(AgentAction::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(AgentPlan::class);
    }
}
