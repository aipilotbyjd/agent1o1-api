<?php

use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Notifications\Billing\PaymentFailedNotification;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;

it('notifies workspace owners and admins, but not other members, on a failed invoice charge', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workspace->forceFill(['stripe_id' => 'cus_test_failed'])->save();

    $admin = User::factory()->create();
    $workspace->members()->create(['user_id' => $admin->id, 'role' => Role::Admin, 'joined_at' => now()]);

    $member = User::factory()->create();
    $workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);

    $payload = [
        'id' => 'evt_test_invoice_failed_1',
        'type' => 'invoice.payment_failed',
        'data' => [
            'object' => [
                'id' => 'in_test_1',
                'customer' => 'cus_test_failed',
            ],
        ],
    ];

    $this->postJson('/api/stripe/webhook', $payload)->assertOk();

    Notification::assertSentTo([$owner, $admin], PaymentFailedNotification::class);
    Notification::assertNotSentTo($member, PaymentFailedNotification::class);
});
