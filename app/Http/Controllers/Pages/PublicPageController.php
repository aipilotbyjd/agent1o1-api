<?php

namespace App\Http\Controllers\Pages;

use App\Enums\Workflows\PageAccess;
use App\Exceptions\UsageBlockedException;
use App\Exceptions\WorkflowValidationException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workflows\WorkflowPageEvent;
use App\Models\Workflows\WorkflowPageSubmission;
use App\Services\Workflows\Pages\PageAccessTokens;
use App\Services\Workflows\Pages\PageInsights;
use App\Services\Workflows\Pages\PagePresenter;
use App\Services\Workflows\Pages\PageSharePreview;
use App\Services\Workflows\Pages\PageSubmissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A workflow page at its public link. No session and no API key: the token
 * in the URL finds the page, a password page also wants the access token
 * `unlock()` hands out, and a result is read with the secret its submission
 * returned. A page that is private, unpublished or switched off answers 404,
 * whether it exists is not something a stranger gets to learn.
 *
 * Problems that are the owner's to fix (out of credits, the workflow no
 * longer publishable) are reported to visitors only as "can't run right now".
 */
class PublicPageController extends Controller
{
    public const string SECRET_HEADER = 'X-Submission-Secret';

    public function __construct(
        private readonly PagePresenter $presenter,
        private readonly PageSubmissions $submissions,
        private readonly PageAccessTokens $tokens,
        private readonly PageInsights $insights,
    ) {}

    /**
     * The page as this visitor gets it (version B when an A/B test puts
     * them there) counted as a view.
     */
    public function show(Request $request, string $token)
    {
        $page = $this->available($token);
        $visitor = $this->insights->visitor($page, $request);
        $variant = $this->insights->variantFor($page, $visitor);

        $this->insights->record($page, WorkflowPageEvent::View, $variant, $visitor);

        return ApiResponse::success([
            'page' => $this->presenter->present($page, $page->contentFor($variant), locked: ! $this->unlocked($page, $request)),
        ]);
    }

    /**
     * The visitor started filling in the page, or clicked its badge.
     */
    public function event(Request $request, string $token)
    {
        $page = $this->available($token);
        $validated = $request->validate(['type' => ['required', Rule::in(WorkflowPageEvent::REPORTABLE)]]);
        $visitor = $this->insights->visitor($page, $request);

        $this->insights->record($page, $validated['type'], $this->insights->variantFor($page, $visitor), $visitor);

        return ApiResponse::noContent();
    }

    /**
     * The page's link card, for the server that renders the page's HTML to
     * put in its head. Answers for a password page too: its title and
     * description are what the owner chose to show before the password.
     */
    public function meta(string $token, PageSharePreview $preview)
    {
        return ApiResponse::success(['meta' => $preview->for($this->available($token))]);
    }

    public function unlock(Request $request, string $token)
    {
        $page = $this->available($token);

        abort_if($page->access !== PageAccess::Password, 404);

        $validated = $request->validate(['password' => ['required', 'string', 'max:200']]);
        $access = $this->tokens->attempt($page, $validated['password']);

        if ($access === null) {
            return ApiResponse::validationError(['password' => ["That password isn't right."]]);
        }

        $variant = $this->insights->variantFor($page, $this->insights->visitor($page, $request));

        return ApiResponse::success([
            'access_token' => $access,
            'page' => $this->presenter->present($page, $page->contentFor($variant)),
        ]);
    }

    public function submit(Request $request, string $token)
    {
        $page = $this->available($token);

        abort_if(! $this->unlocked($page, $request), 403, 'Enter the password to use this page.');

        $validated = $request->validate(['input' => ['nullable', 'array']]);

        try {
            [$submission, $secret] = $this->submissions->submit(
                $page,
                $validated['input'] ?? [],
                null,
                hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
                variant: $this->insights->variantFor($page, $this->insights->visitor($page, $request)),
            );
        } catch (UsageBlockedException|WorkflowValidationException) {
            return ApiResponse::error("This page can't run right now. Please try again later.", 503);
        }

        return ApiResponse::success([
            'submission' => $this->submissions->result($submission, $page->contentFor($submission->variant) ?? $page->published_content, forMember: false),
            'secret' => $secret,
        ], 'Run started.', 202);
    }

    public function result(Request $request, string $token, string $submission)
    {
        $page = $this->available($token);

        $found = WorkflowPageSubmission::query()
            ->where('workflow_page_id', $page->id)
            ->whereKey($submission)
            ->with('run')
            ->first();

        abort_if($found === null || ! $this->submissions->secretMatches($found, $request->header(self::SECRET_HEADER)), 404);

        return ApiResponse::success([
            'submission' => $this->submissions->result($found, $page->contentFor($found->variant) ?? $page->published_content, forMember: false),
        ]);
    }

    private function available(string $token): WorkflowPage
    {
        $page = WorkflowPage::query()->where('public_token', $token)->with(['workflow', 'workspace:id,name', 'creator'])->first();

        abort_if($page === null || ! $page->isPubliclyAvailable() || $page->workflow === null, 404, 'This page is not available.');

        return $page;
    }

    private function unlocked(WorkflowPage $page, Request $request): bool
    {
        return $page->access !== PageAccess::Password
            || $this->tokens->allows($page, $request->header(PageAccessTokens::HEADER));
    }
}
