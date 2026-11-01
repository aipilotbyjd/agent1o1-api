<?php

namespace App\Enums\Auth;

/**
 * Every security-relevant thing that can happen to an account, as written to
 * the `auth_events` log by `AuthEventRecorder`.
 *
 * `notifiesUser()` marks the subset that also emails the account owner. The
 * test is "would a stranger doing this to my account matter to me?" - so a
 * successful sign-in from a new device counts, but an ordinary sign-out does
 * not, and neither does a single failed password attempt (only the lockout
 * that a run of them triggers).
 */
enum AuthEvent: string
{
    case Registered = 'registered';
    case LoggedIn = 'logged_in';
    case LoginFailed = 'login_failed';
    case AccountLocked = 'account_locked';
    case LoggedOut = 'logged_out';
    case LoggedOutAll = 'logged_out_all';
    case SessionRevoked = 'session_revoked';
    case PasswordChanged = 'password_changed';
    case PasswordResetRequested = 'password_reset_requested';
    case PasswordReset = 'password_reset';
    case EmailChangeRequested = 'email_change_requested';
    case EmailChanged = 'email_changed';
    case EmailVerified = 'email_verified';
    case TwoFactorEnabled = 'two_factor_enabled';
    case TwoFactorDisabled = 'two_factor_disabled';
    case TwoFactorChallengeFailed = 'two_factor_challenge_failed';
    case RecoveryCodesRegenerated = 'recovery_codes_regenerated';
    case SocialAccountLinked = 'social_account_linked';
    case AccountDeleted = 'account_deleted';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Account created',
            self::LoggedIn => 'Signed in',
            self::LoginFailed => 'Failed sign-in attempt',
            self::AccountLocked => 'Account temporarily locked',
            self::LoggedOut => 'Signed out',
            self::LoggedOutAll => 'Signed out of all devices',
            self::SessionRevoked => 'Session revoked',
            self::PasswordChanged => 'Password changed',
            self::PasswordResetRequested => 'Password reset requested',
            self::PasswordReset => 'Password reset',
            self::EmailChangeRequested => 'Email change requested',
            self::EmailChanged => 'Email address changed',
            self::EmailVerified => 'Email address verified',
            self::TwoFactorEnabled => 'Two-factor authentication enabled',
            self::TwoFactorDisabled => 'Two-factor authentication disabled',
            self::TwoFactorChallengeFailed => 'Failed two-factor code',
            self::RecoveryCodesRegenerated => 'Recovery codes regenerated',
            self::SocialAccountLinked => 'Social account linked',
            self::AccountDeleted => 'Account deleted',
        };
    }

    /**
     * Whether the account owner is emailed when this happens.
     *
     * `EmailChanged` is deliberately absent: by the time it fires the user's
     * address is already the new one, so `AuthService::confirmEmailChange()`
     * routes that alert to the old address itself.
     */
    public function notifiesUser(): bool
    {
        return match ($this) {
            self::AccountLocked,
            self::EmailChangeRequested,
            self::PasswordChanged,
            self::PasswordReset,
            self::TwoFactorEnabled,
            self::TwoFactorDisabled,
            self::RecoveryCodesRegenerated,
            self::LoggedOutAll,
            self::SocialAccountLinked => true,
            default => false,
        };
    }

    /**
     * What the owner should do if they did not perform the action themselves.
     */
    public function remediation(): string
    {
        return match ($this) {
            self::AccountLocked => 'If this was not you, someone is trying to guess your password. Change it once the lock lifts, and turn on two-factor authentication.',
            self::PasswordChanged, self::PasswordReset => 'If this was not you, reset your password immediately and sign out of every device.',
            self::EmailChangeRequested => 'If this was not you, change your password now: whoever requested this is trying to take the account over, and confirming the new address would let them reset your password.',
            self::EmailChanged => 'If this was not you, contact support straight away: whoever made this change can now request password resets.',
            self::TwoFactorDisabled => 'If this was not you, turn two-factor authentication back on and change your password.',
            self::TwoFactorEnabled, self::RecoveryCodesRegenerated => 'If this was not you, change your password and review your active sessions.',
            self::LoggedOutAll => 'If this was not you, change your password and review your active sessions.',
            self::SocialAccountLinked => 'If this was not you, unlink the provider and change your password.',
            default => 'If this was not you, change your password and review your active sessions.',
        };
    }
}
