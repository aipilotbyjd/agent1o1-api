<?php

namespace App\Models\Secrets;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Secrets\SecretFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A workspace-scoped named value a workflow author references from any
 * node's `config` as `{{ secrets.MY_KEY }}` (or `{{ vars.MY_KEY }}`: same
 * store, same row), substituted in only at run time by `SecretResolver`. The
 * graph, its published versions, and every API response keep the
 * placeholder rather than the value.
 *
 * Secrets and variables are deliberately one model: workspace scoping, CRUD,
 * permissions and template resolution are identical for both, and `is_secret`
 * is the only thing that differs:
 *
 * - `is_secret = true` (the default): write-only. The value never leaves the
 *   server once stored: `#[Hidden]` keeps it out of `toArray()`/`toJson()`,
 *   `SecretResource` omits it, and `SecretRedactor` scrubs it from persisted
 *   node output and error messages.
 * - `is_secret = false`: a plain variable; readable back through the API and
 *   left alone in run output, for non-sensitive config like a base URL.
 *
 * Unlike `ConnectorCredential` this is not tied to a `Connector` and carries
 * no shape of its own: one opaque string, usable by any node.
 */
#[Fillable(['workspace_id', 'created_by', 'key', 'description', 'value', 'is_secret', 'last_used_at'])]
#[Hidden(['value'])]
class Secret extends Model
{
    /** @use HasFactory<SecretFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_secret' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
            'is_secret' => 'boolean',
            'last_used_at' => 'datetime',
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
