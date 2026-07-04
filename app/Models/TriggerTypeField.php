<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'trigger_type_id',
    'field_name',
    'field_label',
    'field_type',
    'is_required',
    'is_secret',
    'placeholder',
    'help_text',
    'validation_regex',
    'options',
    'sort_order',
])]
class TriggerTypeField extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_secret' => 'boolean',
            'options' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function triggerType(): BelongsTo
    {
        return $this->belongsTo(TriggerType::class, 'trigger_type_id');
    }
}
