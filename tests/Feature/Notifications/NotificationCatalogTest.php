<?php

use App\Enums\Notifications\NotificationCategory;
use App\Enums\Notifications\NotificationScope;
use App\Enums\Notifications\NotificationType;
use App\Notifications\AppNotification;

arch('member-facing notifications extend AppNotification, so their channels come from preferences')
    ->expect('App\Notifications')
    ->classes()
    ->toExtend(AppNotification::class)
    ->ignoring([
        AppNotification::class,
        'App\Notifications\Channels',
        'App\Notifications\Definitions',
        // Operator alerts and transactional mail never read member preferences.
        'App\Notifications\Account',
        'App\Notifications\Admin',
        'App\Notifications\SuperAdmin',
        'App\Notifications\Intelligence',
        'App\Notifications\Auth\ConfirmEmailChangeNotification',
        'App\Notifications\Workflows\PublishRequestLink',
    ]);

test('every event is filed under a category the settings page lists', function (NotificationType $event) {
    $definition = $event->definition();

    expect(NotificationType::inCategory($definition->category))->toContain($event)
        ->and($event->label())->not->toBe('')
        ->and($event->description())->not->toBe('');
})->with(NotificationType::cases());

test('account-level categories hold only account-scoped events', function (NotificationCategory $category) {
    $scopes = collect(NotificationType::inCategory($category))->map(fn (NotificationType $event) => $event->definition()->scope)->unique();

    expect($scopes->all())->toBe($category->isAccountLevel() ? [NotificationScope::Account] : [NotificationScope::Workspace]);
})->with(fn () => array_filter(NotificationCategory::cases(), fn (NotificationCategory $category): bool => NotificationType::inCategory($category) !== []));

test('a grouped event names data its notifications carry', function () {
    expect(NotificationType::RunFailed->definition()->grouping?->by)->toBe('workflow_id')
        ->and(NotificationType::ConnectorCredentialExpired->definition()->grouping?->by)->toBe('connector_credential_id');
});

it('links a workspace event into the app and leaves an account event without a workspace unlinked', function () {
    expect(NotificationType::ConnectorCredentialExpired->actionPath('ws-1', ['connector_credential_id' => 'cred-1']))->toBe('/ws-1/apps?account=cred-1')
        ->and(NotificationType::EvalRegressed->actionPath('ws-1', []))->toBeNull()
        ->and(NotificationType::SecurityAlert->actionPath(null, []))->toBeNull()
        ->and(NotificationType::PlatformMessage->actionPath(null, ['action_url' => '/:workspace/apps']))->toBe('/:workspace/apps');
});
