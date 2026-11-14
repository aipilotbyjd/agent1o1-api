<?php

namespace App\Models\Changelog;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member has opened (or dismissed) a changelog entry.
 */
#[Fillable(['changelog_entry_id', 'user_id', 'read_at'])]
class ChangelogRead extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<ChangelogEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(ChangelogEntry::class, 'changelog_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
