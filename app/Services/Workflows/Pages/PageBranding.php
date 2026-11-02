<?php

namespace App\Services\Workflows\Pages;

use App\Enums\Billing\Feature;
use App\Models\Workflows\WorkflowPage;
use App\Services\Referrals\ReferralCodes;
use App\Services\Referrals\ReferralSettings;

/**
 * The "Made with Agent1o1" badge at the foot of a workflow page.
 *
 * The page's theme carries the owner's choice (`show_branding`), but only a
 * plan with `Feature::RemoveBranding` can act on it: on any other plan the
 * badge shows whatever the theme says, so a downgrade brings it back without
 * touching the page.
 *
 * The badge links to signup with the page creator's `?ref=` code, so every
 * visitor it brings in is attributed (and rewarded) through the referral
 * program, plus UTM tags that say which page they came from.
 *
 * @phpstan-type Branding array{show: bool, url: ?string}
 */
class PageBranding
{
    public const string SIGNUP_PATH = '/signup';

    public const string UTM_SOURCE = 'page_badge';

    public const string UTM_MEDIUM = 'referral';

    public const string UTM_CAMPAIGN = 'workflow_page';

    public function __construct(
        private readonly ReferralCodes $codes,
        private readonly ReferralSettings $settings,
    ) {}

    /**
     * Whether the workspace's plan lets its pages hide the badge.
     */
    public function canRemove(WorkflowPage $page): bool
    {
        return (bool) $page->workspace?->currentPlan()?->hasFeature(Feature::RemoveBranding);
    }

    /**
     * @param  array{theme?: array<string, mixed>}  $content
     */
    public function shows(WorkflowPage $page, array $content): bool
    {
        if ((bool) ($content['theme']['show_branding'] ?? true)) {
            return true;
        }

        return ! $this->canRemove($page);
    }

    /**
     * The badge as a visitor's page renders it.
     *
     * @param  array{theme?: array<string, mixed>}  $content
     * @return Branding
     */
    public function for(WorkflowPage $page, array $content): array
    {
        $show = $this->shows($page, $content);

        return ['show' => $show, 'url' => $show ? $this->url($page) : null];
    }

    /**
     * Signup on the frontend, with the creator's referral code when the
     * program is on and the code still works.
     */
    public function url(WorkflowPage $page): string
    {
        $query = array_filter([
            'ref' => $this->referralCode($page),
            'utm_source' => self::UTM_SOURCE,
            'utm_medium' => self::UTM_MEDIUM,
            'utm_campaign' => self::UTM_CAMPAIGN,
            'utm_content' => $page->id,
        ]);

        return rtrim((string) config('app.frontend_url'), '/').self::SIGNUP_PATH.'?'.http_build_query($query);
    }

    private function referralCode(WorkflowPage $page): ?string
    {
        $creator = $page->creator;

        if ($creator === null || ! $this->settings->enabled()) {
            return null;
        }

        $code = $this->codes->forUser($creator);

        return $code->isUsable() ? $code->code : null;
    }
}
