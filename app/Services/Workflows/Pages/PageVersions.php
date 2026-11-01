<?php

namespace App\Services\Workflows\Pages;

use App\Models\User;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workflows\WorkflowPageVersion;
use Illuminate\Support\Facades\DB;

/**
 * A page's version history. Versions are numbered per page; the newest
 * `KEEP` are kept. Restoring one saves the current draft first, so a
 * restore can itself be undone.
 */
class PageVersions
{
    public const int KEEP = 100;

    /**
     * Saves the page's current drafts (or `$content` and `$variantContent`
     * when given, as on publish) as the next version.
     *
     * @param  array<string, mixed>|null  $content
     * @param  array<string, mixed>|null  $variantContent
     */
    public function snapshot(WorkflowPage $page, string $kind, ?User $user, ?string $label = null, ?array $content = null, ?array $variantContent = null): WorkflowPageVersion
    {
        return DB::transaction(function () use ($page, $kind, $user, $label, $content, $variantContent): WorkflowPageVersion {
            // Locking the page row keeps two saves from taking the same number;
            // Postgres can't lock rows that an aggregate reads.
            WorkflowPage::query()->whereKey($page->id)->lockForUpdate()->first();
            $last = (int) WorkflowPageVersion::query()->where('workflow_page_id', $page->id)->max('number');

            $version = $page->versions()->create([
                'number' => $last + 1,
                'kind' => $kind,
                'label' => filled($label) ? trim($label) : null,
                'content' => $content ?? $page->content,
                'variant_content' => $content === null ? $page->variant_content : $variantContent,
                'created_by' => $user?->id,
                'created_at' => now(),
            ]);

            $page->versions()->where('number', '<=', $last + 1 - self::KEEP)->delete();

            return $version;
        });
    }

    /**
     * Puts a version back as the page's draft, and version B's, when the
     * version has one and the page still runs a test. Nothing goes live
     * until the page is published.
     */
    public function restore(WorkflowPage $page, WorkflowPageVersion $version, User $user): WorkflowPage
    {
        abort_if($version->workflow_page_id !== $page->id, 404);

        return DB::transaction(function () use ($page, $version, $user): WorkflowPage {
            $this->snapshot($page, WorkflowPageVersion::BeforeRestore, $user, "Before restoring version {$version->number}");

            $page->content = $version->content;

            if ($version->variant_content !== null && $page->variant_content !== null) {
                $page->variant_content = $version->variant_content;
            }

            $page->save();

            return $page;
        });
    }
}
