<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'description',
    'icon',
    'category_type',
    'is_active',
])]
class TriggerCategory extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function triggerTypes(): HasMany
    {
        return $this->hasMany(TriggerType::class, 'category_id');
    }

    public function isAppSpecific(): bool
    {
        return $this->category_type === 'app_specific';
    }
}
