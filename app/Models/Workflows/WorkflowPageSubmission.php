<?php

namespace App\Models\Workflows;

use App\Models\Runs\Run;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One use of a workflow page: the run it started and who started it. A
 * member is recorded by `user_id`; an anonymous visitor only by a hash of
 * their address, kept to enforce the page's limits, and by the hash of the
 * secret handed back on submit: the only thing that lets them read the
 * result. `is_test` marks runs started from the builder's preview, which
 * don't count toward the page's daily limit. `variant` is the version of the
 * page (`a`, or `b` during an A/B test) the submission came from.
 */
#[Fillable(['workspace_id', 'workflow_page_id', 'run_id', 'user_id', 'visitor_hash', 'secret_hash', 'is_test', 'variant', 'input'])]
#[Hidden(['secret_hash', 'visitor_hash'])]
class WorkflowPageSubmission extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_test' => 'boolean',
            'input' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(WorkflowPage::class, 'workflow_page_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
