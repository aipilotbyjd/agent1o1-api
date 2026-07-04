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
    'icon',
    'color',
    'items',
    'is_featured',
    'is_active',
    'sort_order',
])]
class TemplateCollection extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
