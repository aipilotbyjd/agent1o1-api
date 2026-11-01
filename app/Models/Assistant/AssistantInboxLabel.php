<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantInboxLabelGroup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A label the owner defined in plain words. `definition` is what the
 * classifier reads; `group` decides whether labelled mail stays in the
 * inbox. Built-in labels have a `key` and can be edited but not deleted.
 */
#[Fillable(['assistant_inbox_config_id', 'key', 'name', 'definition', 'color', 'group', 'enabled', 'provider_label_id', 'position'])]
class AssistantInboxLabel extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group' => AssistantInboxLabelGroup::class,
            'enabled' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(AssistantInboxConfig::class, 'assistant_inbox_config_id');
    }

    public function isBuiltin(): bool
    {
        return $this->key !== null;
    }
}
