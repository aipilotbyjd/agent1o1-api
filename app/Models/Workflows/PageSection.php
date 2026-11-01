<?php

namespace App\Models\Workflows;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Workflows\PageSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A block saved to the workspace's library (usually a whole section) to
 * drop into any page. `block` is one block of a page's content tree, in the
 * shape `PageSchema::normalize()` gives it; it gets new ids when it's used.
 */
#[Fillable(['workspace_id', 'created_by', 'name', 'block'])]
class PageSection extends Model
{
    /** @use HasFactory<PageSectionFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'block' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
