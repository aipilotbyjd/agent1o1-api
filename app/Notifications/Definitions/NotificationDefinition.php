<?php

namespace App\Notifications\Definitions;

use App\Enums\Notifications\NotificationCategory;
use App\Enums\Notifications\NotificationScope;
use App\Enums\Notifications\NotificationSeverity;

/**
 * Everything the platform knows about one kind of notification, in one place:
 * which section it is filed under, how loud it is, who decides its channels,
 * what it does by default, whether a member may switch it off, and how
 * repeats are folded together.
 *
 * Built by `NotificationEvent::definition()`. Notification classes only
 * supply content (title, body, data); they never choose channels.
 */
final readonly class NotificationDefinition
{
    /**
     * @param  bool  $mandatory  The member can't switch it off: its defaults always apply.
     * @param  bool  $configurable  Listed on the settings page. Off for events whose delivery someone else decides (a super admin, or the marketing opt-out).
     * @param  bool  $needsAction  Someone has to decide or fix something: what the bell's "Needs action" filter shows.
     * @param  string|null  $actionLabel  What following the notification does, e.g. "View run".
     */
    public function __construct(
        public NotificationCategory $category,
        public NotificationSeverity $severity,
        public NotificationScope $scope = NotificationScope::Workspace,
        public bool $defaultInApp = true,
        public bool $defaultEmail = false,
        public bool $mandatory = false,
        public bool $configurable = true,
        public bool $needsAction = false,
        public ?NotificationGrouping $grouping = null,
        public ?string $actionLabel = null,
    ) {}

    /**
     * @return array{in_app: bool, email: bool}
     */
    public function defaults(): array
    {
        return ['in_app' => $this->defaultInApp, 'email' => $this->defaultEmail];
    }
}
