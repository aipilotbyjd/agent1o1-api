<?php

namespace App\Models\Assistant;

use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The platform's Slack app installed in one Slack workspace, linked to the
 * agent1o1 workspace whose members it answers in DMs.
 */
#[Fillable(['slack_team_id', 'team_name', 'bot_token', 'bot_user_id', 'workspace_id', 'installed_by'])]
class AssistantSlackInstall extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $hidden = ['bot_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bot_token' => 'encrypted',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
