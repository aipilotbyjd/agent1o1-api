<?php

use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Referrals\ReferralCode;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workflows\WorkflowPageEvent;
use App\Services\Workflows\Pages\PageInsights;
use App\Services\Workflows\Pages\PageSchema;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    config(['app.frontend_url' => 'https://app.agent1o1.test', 'referrals.enabled' => true]);

    $this->owner = User::factory()->create(['name' => 'Priya Shah']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);

    $this->grantPlan = function (array $features): void {
        PlanGrant::factory()->forWorkspace($this->workspace)
            ->forPlan(Plan::factory()->create(['features' => ['public_pages' => true, ...$features], 'credits_monthly' => 1_000_000]))
            ->active()
            ->create();
    };

    $this->workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
    $this->workflow->replaceGraph([
        'nodes' => [
            ['key' => 'topic', 'type' => 'workflow_input', 'config' => ['name' => 'topic', 'required' => true], 'position' => ['x' => 0, 'y' => 0]],
            ['key' => 'note_out', 'type' => 'workflow_output', 'config' => ['name' => 'note', 'value' => 'About {{ nodes.topic.value }}'], 'position' => ['x' => 300, 'y' => 0]],
        ],
        'edges' => [['from' => 'topic', 'to' => 'note_out']],
    ]);
    $this->workflow->publishVersion(publisher: $this->owner);

    $this->publishWithBranding = function (bool $showBranding): void {
        $content = app(PageSchema::class)->normalize([
            'blocks' => [
                ['id' => 'topic', 'type' => 'field', 'props' => ['field_key' => 'topic']],
                ['id' => 'go', 'type' => 'submit', 'props' => []],
            ],
            'theme' => ['show_branding' => $showBranding],
        ]);

        $this->page->forceFill(['content' => $content, 'published_content' => $content, 'published_by' => $this->owner->id, 'published_at' => now()])->save();
    };

    $this->page = WorkflowPage::query()->create([
        'workspace_id' => $this->workspace->id,
        'workflow_id' => $this->workflow->id,
        'created_by' => $this->owner->id,
        'name' => 'Notes',
        'content' => app(PageSchema::class)->normalize(['blocks' => []]),
        'access' => 'link',
    ]);

    $this->public = "/api/v1/p/{$this->page->public_token}";
    $this->base = "/api/v1/workspaces/{$this->workspace->id}/pages/{$this->page->id}";
});

it('keeps the badge on a plan that cannot remove it, even when the page hides it', function () {
    ($this->grantPlan)([]);
    ($this->publishWithBranding)(false);

    $page = $this->getJson($this->public)->assertOk()->json('data.page');

    expect($page['branding']['show'])->toBeTrue();
    expect($page['theme']['show_branding'])->toBeTrue();
});

it('hides the badge when the plan allows it and the page turns it off', function () {
    ($this->grantPlan)(['remove_branding' => true]);
    ($this->publishWithBranding)(false);

    $page = $this->getJson($this->public)->assertOk()->json('data.page');

    expect($page['branding'])->toBe(['show' => false, 'url' => null]);
    expect($page['theme']['show_branding'])->toBeFalse();
});

it('shows the badge when the plan allows removing it but the page keeps it', function () {
    ($this->grantPlan)(['remove_branding' => true]);
    ($this->publishWithBranding)(true);

    expect($this->getJson($this->public)->assertOk()->json('data.page.branding.show'))->toBeTrue();
});

it('links the badge to signup with the creator’s referral code and the page’s UTM tags', function () {
    ($this->grantPlan)([]);
    ($this->publishWithBranding)(true);

    $url = $this->getJson($this->public)->assertOk()->json('data.page.branding.url');
    $code = ReferralCode::query()->where('user_id', $this->owner->id)->value('code');

    expect($code)->not->toBeNull();
    expect(strtok($url, '?'))->toBe('https://app.agent1o1.test/signup');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query)->toBe([
        'ref' => $code,
        'utm_source' => 'page_badge',
        'utm_medium' => 'referral',
        'utm_campaign' => 'workflow_page',
        'utm_content' => $this->page->id,
    ]);
});

it('leaves the referral code out when the program is off', function () {
    config(['referrals.enabled' => false]);
    ($this->grantPlan)([]);
    ($this->publishWithBranding)(true);

    $url = $this->getJson($this->public)->assertOk()->json('data.page.branding.url');

    expect($url)->not->toContain('ref=')->toContain('utm_source=page_badge');
});

it('leaves the referral code out when the creator’s code no longer works', function () {
    ($this->grantPlan)([]);
    ($this->publishWithBranding)(true);
    ReferralCode::factory()->create(['user_id' => $this->owner->id, 'code' => 'priya-off', 'is_active' => false]);

    expect($this->getJson($this->public)->assertOk()->json('data.page.branding.url'))->not->toContain('ref=');
});

it('counts badge clicks once per visitor a day in the page insights', function () {
    ($this->grantPlan)([]);
    ($this->publishWithBranding)(true);
    $headers = [PageInsights::VISITOR_HEADER => 'visitor-one-123'];

    $this->postJson("{$this->public}/events", ['type' => 'badge_click'], $headers)->assertNoContent();
    $this->postJson("{$this->public}/events", ['type' => 'badge_click'], $headers)->assertNoContent();
    $this->postJson("{$this->public}/events", ['type' => 'badge_click'], [PageInsights::VISITOR_HEADER => 'visitor-two-456'])->assertNoContent();

    expect(WorkflowPageEvent::query()->where('type', WorkflowPageEvent::BadgeClick)->count())->toBe(2);

    Passport::actingAs($this->owner);
    $totals = $this->getJson("{$this->base}/insights?days=7")->assertOk()->json('data.insights.totals');

    expect($totals['badge_clicks'])->toBe(2);
    expect($totals['views'])->toBe(0);
});

it('tells the builder whether the plan can remove the badge', function () {
    ($this->grantPlan)([]);
    Passport::actingAs($this->owner);

    expect($this->getJson($this->base)->assertOk()->json('data.page.branding.can_remove'))->toBeFalse();

    PlanGrant::query()->delete();
    ($this->grantPlan)(['remove_branding' => true]);

    expect($this->getJson($this->base)->assertOk()->json('data.page.branding.can_remove'))->toBeTrue();
});
