<?php

namespace App\Models\Auth;

use App\Enums\Auth\ApiKeyAbility;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['workspace_id', 'name', 'hashed_key', 'abilities', 'expires_at'])]
#[Hidden(['hashed_key'])]
class ApiKey extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public static function hash(string $plainTextKey): string
    {
        return hash('sha256', $plainTextKey);
    }

    public static function generatePlainTextKey(): string
    {
        return 'ak_'.Str::random(40);
    }

    public function hasAbility(ApiKeyAbility $ability): bool
    {
        return in_array(ApiKeyAbility::All->value, $this->abilities, strict: true)
            || in_array($ability->value, $this->abilities, strict: true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
