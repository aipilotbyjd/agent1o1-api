<?php

namespace App\Enums\SuperAdmin;

/**
 * What `super_admin_login_events` records: the sign-in side of a super
 * admin's account. Changes made *to* data go to the audit log instead.
 */
enum SuperAdminAuthEvent: string
{
    case LoginSucceeded = 'login_succeeded';
    case LoginFailed = 'login_failed';
    case TwoFactorFailed = 'two_factor_failed';
    case LockedOut = 'locked_out';
    case IpBlocked = 'ip_blocked';
    case TokenRefreshed = 'token_refreshed';
    case RefreshReuseDetected = 'refresh_reuse_detected';
    case LoggedOut = 'logged_out';
    case LoggedOutAll = 'logged_out_all';
    case SessionRevoked = 'session_revoked';
    case PasswordChanged = 'password_changed';
    case TwoFactorEnabled = 'two_factor_enabled';
    case TwoFactorConfirmed = 'two_factor_confirmed';
    case RecoveryCodesRegenerated = 'recovery_codes_regenerated';
}
