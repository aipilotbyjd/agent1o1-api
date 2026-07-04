<?php

use App\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('a non-admin cannot view platform settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/admin/settings')
        ->assertForbidden();
});

test('an admin can read and update platform settings', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin, 'api')
        ->putJson('/api/v1/admin/settings', [
            'settings' => ['signups_enabled' => false, 'banner' => 'Maintenance tonight'],
        ])
        ->assertOk()
        ->assertJsonPath('data.signups_enabled', false);

    expect(PlatformSetting::get('banner'))->toBe('Maintenance tonight');
});
