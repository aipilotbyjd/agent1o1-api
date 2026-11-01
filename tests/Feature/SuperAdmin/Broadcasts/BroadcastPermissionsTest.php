<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Broadcasts\Announcement;
use App\Models\Broadcasts\BroadcastCampaign;

it('lets every role read announcements and campaigns', function (SuperAdminRole $role) {
    $headers = superAdminHeaders(superAdmin($role));

    $this->withHeaders($headers)->getJson(saUrl('broadcasts/announcements'))->assertOk();
    $this->withHeaders($headers)->getJson(saUrl('broadcasts/campaigns'))->assertOk();
})->with(array_map(fn (SuperAdminRole $role) => [$role], SuperAdminRole::cases()));

it('lets only owners and operators write', function (SuperAdminRole $role, int $status) {
    $headers = superAdminHeaders(superAdmin($role));

    $this->withHeaders($headers)
        ->postJson(saUrl('broadcasts/announcements'), ['title' => 'Hello'])
        ->assertStatus($status);

    $this->withHeaders($headers)
        ->postJson(saUrl('broadcasts/campaigns'), ['name' => 'Launch', 'subject' => 'Hi', 'body_markdown' => 'Body'])
        ->assertStatus($status);

    $this->withHeaders($headers)
        ->postJson(saUrl('broadcasts/audience/preview'), [])
        ->assertStatus($status === 201 ? 200 : $status);
})->with([
    'owner' => [SuperAdminRole::Owner, 201],
    'operator' => [SuperAdminRole::Operator, 201],
    'finance' => [SuperAdminRole::Finance, 403],
    'support' => [SuperAdminRole::Support, 403],
    'viewer' => [SuperAdminRole::Viewer, 403],
]);

it('keeps a view-only role from changing or sending anything that exists', function () {
    $announcement = Announcement::factory()->create();
    $campaign = BroadcastCampaign::factory()->create();
    $headers = superAdminHeaders(superAdmin(SuperAdminRole::Viewer));

    $this->withHeaders($headers)->patchJson(saUrl("broadcasts/announcements/{$announcement->id}"), ['title' => 'x'])->assertForbidden();
    $this->withHeaders($headers)->postJson(saUrl("broadcasts/announcements/{$announcement->id}/publish"))->assertForbidden();
    $this->withHeaders($headers)->deleteJson(saUrl("broadcasts/announcements/{$announcement->id}"))->assertForbidden();
    $this->withHeaders($headers)->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/test-send"))->assertForbidden();
    $this->withHeaders($headers)->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/start"), ['expected_recipient_count' => 1])->assertForbidden();
    $this->withHeaders($headers)->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/cancel"))->assertForbidden();

    expect(Announcement::query()->count())->toBe(1)->and($campaign->fresh()->status->value)->toBe('draft');
});

it('turns away an unauthenticated request', function () {
    $this->getJson(saUrl('broadcasts/campaigns'))->assertUnauthorized();
});
