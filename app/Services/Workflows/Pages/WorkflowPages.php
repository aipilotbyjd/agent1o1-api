<?php

namespace App\Services\Workflows\Pages;

use App\Enums\Billing\Feature;
use App\Enums\Billing\PlanLimit;
use App\Enums\Workflows\PageAccess;
use App\Enums\Workspaces\AuditAction;
use App\Exceptions\FeatureNotAvailableException;
use App\Models\Templates\PageTemplate;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workflows\WorkflowPageVersion;
use App\Services\Billing\PlanLimitGate;
use App\Services\Workspaces\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Authoring workflow pages: creating one from a workflow, saving the draft,
 * publishing it, and deciding who can open it.
 *
 * Sharing outside the workspace needs `Feature::PublicPages`, and a page
 * behind a password needs one set. Publishing copies the draft over the live
 * page only when `PageSchema::issues()` finds no errors.
 */
class WorkflowPages
{
    public function __construct(
        private readonly PageSchema $schema,
        private readonly PlanLimitGate $limits,
        private readonly AuditLogger $audit,
        private readonly PageVersions $versions,
    ) {}

    /**
     * Without a template the page starts from `PageSchema::starter()`.
     */
    public function create(Workflow $workflow, User $creator, ?string $name = null, ?string $description = null, ?PageTemplate $template = null): WorkflowPage
    {
        $this->limits->assertCanCreate($workflow->workspace, PlanLimit::Pages);

        $name = filled($name) ? $name : $workflow->name;

        $page = $workflow->pages()->create([
            'workspace_id' => $workflow->workspace_id,
            'created_by' => $creator->id,
            'name' => $name,
            'description' => $description,
            'content' => $template === null
                ? $this->schema->starter($workflow, $name)
                : $this->schema->fromTemplate($template->content, $workflow, $name),
        ]);

        $template?->increment('usage_count');

        return $page;
    }

    public function duplicate(WorkflowPage $page, User $creator): WorkflowPage
    {
        $this->limits->assertCanCreate($page->workspace, PlanLimit::Pages);

        return WorkflowPage::query()->create([
            'workspace_id' => $page->workspace_id,
            'workflow_id' => $page->workflow_id,
            'created_by' => $creator->id,
            'name' => "{$page->name} (copy)",
            'description' => $page->description,
            'content' => $page->content,
        ]);
    }

    /**
     * Applies whichever of the page's editable attributes `$changes` holds.
     *
     * @param  array<string, mixed>  $changes
     */
    public function update(WorkflowPage $page, array $changes): WorkflowPage
    {
        $accessBefore = $page->access;

        foreach (['name', 'description', 'is_enabled', 'max_runs_per_day'] as $key) {
            if (array_key_exists($key, $changes)) {
                $page->{$key} = $changes[$key];
            }
        }

        foreach (['share_title', 'share_description', 'share_image_url'] as $key) {
            if (array_key_exists($key, $changes)) {
                $page->{$key} = filled($changes[$key]) ? trim($changes[$key]) : null;
            }
        }

        $savesDraft = array_key_exists('content', $changes) || array_key_exists('variant_content', $changes);

        // A builder sends the revision it loaded; if the draft moved on since,
        // saving would throw away someone else's changes.
        if ($savesDraft && isset($changes['base_revision']) && (int) $changes['base_revision'] !== $page->draft_revision) {
            throw new ConflictHttpException('This page was changed somewhere else since you opened it.');
        }

        if (array_key_exists('content', $changes)) {
            $page->content = $this->schema->normalize($changes['content']);
        }

        if (array_key_exists('variant_content', $changes)) {
            abort_if($page->variant_content === null, 409, 'This page has no version B. Create one first.');
            $page->variant_content = $this->schema->normalize($changes['variant_content']);
        }

        if (array_key_exists('variant_split', $changes)) {
            $page->variant_split = max(1, min(99, (int) $changes['variant_split']));
        }

        if (array_key_exists('workflow_id', $changes) && $changes['workflow_id'] !== $page->workflow_id) {
            $page->workflow_id = $changes['workflow_id'];
        }

        if (array_key_exists('password', $changes) && filled($changes['password'])) {
            $page->password = $changes['password'];
            // A new password signs everyone out, the same as a new link.
            $page->public_token = WorkflowPage::newToken();
        }

        if (array_key_exists('access', $changes)) {
            $access = PageAccess::from($changes['access']);

            if ($access->isPublic() && ! $page->workspace->currentPlan()?->hasFeature(Feature::PublicPages)) {
                throw new FeatureNotAvailableException(Feature::PublicPages);
            }

            if ($access === PageAccess::Password && $page->password === null) {
                throw ValidationException::withMessages(['password' => 'Set a password for this page first.']);
            }

            $page->access = $access;

            if ($access !== PageAccess::Password) {
                $page->password = null;
            }
        }

        $page->save();

        if ($page->access !== $accessBefore) {
            $this->audit->record($page->workspace_id, AuditAction::PageAccessChanged, $page, [
                'from' => $accessBefore->value,
                'to' => $page->access->value,
            ]);
        }

        return $page;
    }

    /**
     * Makes the drafts live: version B too when there is one, which starts
     * (or updates) the A/B test.
     */
    public function publish(WorkflowPage $page, User $publisher): WorkflowPage
    {
        $issues = $this->schema->issues($page->content, $page->workflow);
        $variantIssues = $page->variant_content === null ? [] : $this->schema->issues($page->variant_content, $page->workflow);

        if (PageSchema::hasErrors($issues) || PageSchema::hasErrors($variantIssues)) {
            throw ValidationException::withMessages([
                'content' => [
                    ...collect($issues)->where('severity', 'error')->pluck('message')->values()->all(),
                    ...collect($variantIssues)->where('severity', 'error')->map(fn (array $issue): string => "Version B: {$issue['message']}")->values()->all(),
                ],
            ]);
        }

        $page->forceFill([
            'published_variant_content' => $page->variant_content,
            'published_content' => $page->content,
            'published_at' => now(),
            'published_by' => $publisher->id,
        ])->save();

        $this->versions->snapshot($page, WorkflowPageVersion::Published, $publisher, content: $page->content, variantContent: $page->variant_content);

        $this->audit->record($page->workspace_id, AuditAction::PagePublished, $page, ['name' => $page->name]);

        return $page;
    }

    /**
     * Starts version B as a copy of the page's draft, to change and test
     * against it. It goes live when the page is next published.
     */
    public function createVariant(WorkflowPage $page): WorkflowPage
    {
        abort_if($page->variant_content !== null, 409, 'This page already has a version B.');

        $page->forceFill(['variant_content' => $page->content])->save();

        return $page;
    }

    /**
     * Ends the test and throws version B away. Visitors all get the
     * published page straight away.
     */
    public function removeVariant(WorkflowPage $page): WorkflowPage
    {
        $page->forceFill(['variant_content' => null, 'published_variant_content' => null])->save();

        return $page;
    }

    /**
     * Ends the test with B as the winner: B's draft becomes the page's
     * draft, ready to publish, and visitors get the published page until it
     * is.
     */
    public function promoteVariant(WorkflowPage $page): WorkflowPage
    {
        abort_if($page->variant_content === null, 409, 'This page has no version B.');

        $page->forceFill([
            'content' => $page->variant_content,
            'variant_content' => null,
            'published_variant_content' => null,
        ])->save();

        return $page;
    }

    public function unpublish(WorkflowPage $page): WorkflowPage
    {
        $page->forceFill(['published_content' => null, 'published_variant_content' => null, 'published_at' => null])->save();

        $this->audit->record($page->workspace_id, AuditAction::PageUnpublished, $page, ['name' => $page->name]);

        return $page;
    }

    public function rotateLink(WorkflowPage $page): WorkflowPage
    {
        $page->forceFill(['public_token' => WorkflowPage::newToken()])->save();

        $this->audit->record($page->workspace_id, AuditAction::PageLinkRotated, $page, ['name' => $page->name]);

        return $page;
    }

    public function delete(WorkflowPage $page): void
    {
        DB::transaction(function () use ($page): void {
            $this->audit->record($page->workspace_id, AuditAction::PageDeleted, $page, ['name' => $page->name]);
            $page->delete();
        });
    }
}
