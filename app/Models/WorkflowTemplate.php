<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'slug',
    'description',
    'category',
    'icon',
    'color',
    'tags',
    'nodes_data',
    'edges_data',
    'is_featured',
    'is_active',
    'usage_count',
    'sort_order',
])]
class WorkflowTemplate extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'nodes_data' => 'array',
            'edges_data' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
