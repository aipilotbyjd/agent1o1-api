<?php

namespace App\Models\Nodes;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Nodes\CustomNodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A workspace-defined node: resolved by `NodeRegistry` from a `custom:{id}`
 * type string instead of a PHP `NodeContract` class. Execution machinery
 * (interpreting `config_schema` at runtime) is Stage 11
 * (docs/WORKFLOWS_AGENTS_BUILD_PLAN.md): this model only carries the
 * definition for now.
 */
#[Fillable([
    'workspace_id', 'category_id', 'type', 'name', 'description', 'icon', 'color',
    'config_schema', 'input_schema', 'output_schema', 'credential_type', 'is_active', 'created_by',
])]
class CustomNode extends Model
{
    /** @use HasFactory<CustomNodeFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config_schema' => 'array',
            'input_schema' => 'array',
            'output_schema' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NodeCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
