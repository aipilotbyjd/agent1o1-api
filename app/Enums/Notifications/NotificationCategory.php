<?php

namespace App\Enums\Notifications;

/**
 * The sections a member's notifications are filed under: the bell's filters,
 * the settings page's groups and the level a member can switch a whole group
 * of events on or off at.
 *
 * Every `NotificationType` belongs to exactly one category
 * (`NotificationType::definition()`), so the frontend never has to guess a
 * category from an event key's prefix.
 */
enum NotificationCategory: string
{
    case Workspace = 'workspace';
    case Runs = 'runs';
    case Triggers = 'triggers';
    case Connections = 'connections';
    case Agents = 'agents';
    case Billing = 'billing';
    case Assistant = 'assistant';
    case Referrals = 'referrals';
    case Security = 'security';
    case ProductUpdates = 'product_updates';

    /**
     * The prefix a category-wide preference is stored under in
     * `notification_preferences.event_key` and
     * `account_notification_preferences.key`.
     */
    public const string PREFERENCE_PREFIX = 'category:';

    public function label(): string
    {
        return match ($this) {
            self::Workspace => 'Workspace & members',
            self::Runs => 'Runs & approvals',
            self::Triggers => 'Triggers',
            self::Connections => 'Connections & AI',
            self::Agents => 'Agents',
            self::Billing => 'Billing & credits',
            self::Assistant => 'Assistant',
            self::Referrals => 'Referrals',
            self::Security => 'Security',
            self::ProductUpdates => 'Product updates',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Workspace => 'People joining, leaving or changing roles in the workspace.',
            self::Runs => 'Failed runs, approvals and workflow changes waiting for review.',
            self::Triggers => 'Triggers that were switched off and need attention.',
            self::Connections => 'App connections, AI provider keys and chat or webhook channels.',
            self::Agents => 'Agent approvals, evaluations and reflections.',
            self::Billing => 'Payments, subscriptions, trials and credit usage.',
            self::Assistant => 'Your assistant\'s reports, meeting briefs and follow-up reminders.',
            self::Referrals => 'People joining with your link and the rewards you earn.',
            self::Security => 'Sign-ins and changes to your account. These can\'t be switched off.',
            self::ProductUpdates => 'News, tips and announcements from the team.',
        };
    }

    /**
     * Icon key the frontend maps to its own icon set.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Workspace => 'users',
            self::Runs => 'play',
            self::Triggers => 'zap',
            self::Connections => 'plug',
            self::Agents => 'bot',
            self::Billing => 'credit-card',
            self::Assistant => 'sparkles',
            self::Referrals => 'gift',
            self::Security => 'shield',
            self::ProductUpdates => 'megaphone',
        };
    }

    /**
     * Whether the category's preferences belong to the account rather than to
     * one workspace: its events are about the person, not the workspace.
     */
    public function isAccountLevel(): bool
    {
        return in_array($this, [self::Assistant, self::Security, self::ProductUpdates], true);
    }

    public function preferenceKey(): string
    {
        return self::PREFERENCE_PREFIX.$this->value;
    }

    public static function fromPreferenceKey(string $key): ?self
    {
        return str_starts_with($key, self::PREFERENCE_PREFIX)
            ? self::tryFrom(substr($key, strlen(self::PREFERENCE_PREFIX)))
            : null;
    }

    /**
     * @return array<int, string>
     */
    public static function preferenceKeys(): array
    {
        return array_map(fn (self $category): string => $category->preferenceKey(), self::cases());
    }
}
