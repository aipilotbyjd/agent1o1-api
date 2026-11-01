<?php

namespace App\Models\Templates;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['collection_id', 'templatable_type', 'templatable_id', 'position'])]
class TemplateCollectionItem extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(TemplateCollection::class, 'collection_id');
    }

    public function templatable(): MorphTo
    {
        return $this->morphTo();
    }
}
