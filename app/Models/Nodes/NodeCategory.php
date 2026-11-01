<?php

namespace App\Models\Nodes;

use App\Models\Concerns\TracksAdminEdits;
use App\Models\User;
use Database\Factories\Nodes\NodeCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The product-facing node taxonomy: what the node picker groups by. Every
 * `NodeContract::category()` must return a string matching a row's `slug`
 * here. See docs/STRUCTURE.md's "How node folders tie to NodeCategory".
 */
#[Fillable(['name', 'slug', 'description', 'icon', 'color', 'sort_order', 'kind', 'functions', 'vendor', 'admin_edited_fields', 'status', 'status_note'])]
class NodeCategory extends Model
{
    /** @use HasFactory<NodeCategoryFactory> */
    use HasFactory, HasUuids, TracksAdminEdits;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
        'kind' => 'core',
        'status' => 'operational',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'functions' => 'array',
        ];
    }

    /**
     * The users who pinned this app to their "Your apps" in the editor.
     */
    public function pinnedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'node_category_pins')->withTimestamps();
    }

    public function customNodes(): HasMany
    {
        return $this->hasMany(CustomNode::class, 'category_id');
    }
}
