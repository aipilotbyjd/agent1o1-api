<?php

namespace App\Models;

use App\Casts\Timezone;
use App\Enums\Onboarding\DiscoverySource;
use App\Enums\Onboarding\JobRole;
use App\Enums\Onboarding\OnboardingStep;
use App\Models\Account\DataExport;
use App\Models\Auth\AuthEventLog;
use App\Models\Changelog\ChangelogRead;
use App\Models\Credentials\OAuthConnection;
use App\Models\Nodes\NodeCategory;
use App\Models\Notifications\AccountNotificationPreference;
use App\Models\Notifications\InboxNotification;
use App\Models\Notifications\NotificationPreference;
use App\Models\Onboarding\LifecycleEmailSend;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralCode;
use App\Models\Referrals\ReferralReward;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Timezones\Timezones;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'avatar', 'timezone', 'pending_email', 'pending_email_requested_at', 'current_workspace_id', 'free_workspace_id', 'onboarding_current_step', 'onboarding_skipped_steps', 'onboarding_completed_at', 'onboarding_dismissed_at', 'job_role', 'discovery_source', 'signup_attribution'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasApiTokens, HasFactory, HasUuids, MustVerifyEmailTrait, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'timezone' => Timezone::class.':nullable',
            'pending_email_requested_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'onboarding_current_step' => OnboardingStep::class,
            'onboarding_skipped_steps' => 'array',
            'onboarding_completed_at' => 'datetime',
            'onboarding_dismissed_at' => 'datetime',
            'onboarding_reminder_sent_at' => 'datetime',
            'job_role' => JobRole::class,
            'discovery_source' => DiscoverySource::class,
            'signup_attribution' => 'array',
            'suspended_at' => 'datetime',
            'marketing_opted_out_at' => 'datetime',
        ];
    }

    /**
     * The one spelling an address is stored and looked up by. Mailboxes are
     * case-insensitive in practice, and Postgres compares text exactly, so
     * without this `Bob@x.com` and `bob@x.com` would be two accounts.
     */
    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * Passport's password grant looks the user up by the username it is
     * given: normalized here so it finds the same row login did.
     */
    public function findForPassport(string $username): ?self
    {
        return static::query()->where('email', self::normalizeEmail($username))->first();
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value === null ? null : self::normalizeEmail($value));
    }

    protected function pendingEmail(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value === null ? null : self::normalizeEmail($value));
    }

    /**
     * The picture shown for this account: an uploaded one wins, else the one
     * Google or GitHub gave at sign-in. Null means the app shows initials.
     */
    public function avatarUrl(): ?string
    {
        if ($this->avatar) {
            return Storage::disk('public')->url($this->avatar);
        }

        $connections = $this->relationLoaded('oauthConnections')
            ? $this->oauthConnections->sortByDesc('created_at')
            : $this->oauthConnections()->latest()->get();

        return $connections->firstWhere(fn (OAuthConnection $connection): bool => filled($connection->avatar))?->avatar;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * Locked out by a super admin (see `SuperAdminUserService`).
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Unsubscribed from marketing email. Service messages (an incident, a
     * policy change) are sent regardless: see `BroadcastCategory`.
     */
    public function hasOptedOutOfMarketing(): bool
    {
        return $this->marketing_opted_out_at !== null;
    }

    /**
     * The timezone for what is this member's own: theirs when set, else the
     * workspace's, else UTC.
     */
    public function timezoneIn(?Workspace $workspace): string
    {
        return $this->timezone ?? $workspace?->timezone ?? Timezones::DEFAULT;
    }

    public function hasPendingEmailChange(): bool
    {
        return $this->pending_email !== null;
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    /**
     * @return HasMany<LifecycleEmailSend, $this>
     */
    public function lifecycleEmailSends(): HasMany
    {
        return $this->hasMany(LifecycleEmailSend::class);
    }

    /**
     * @return HasMany<Workspace, $this>
     */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<Workspace, $this>
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * @return HasMany<DataExport, $this>
     */
    public function dataExports(): HasMany
    {
        return $this->hasMany(DataExport::class);
    }

    /**
     * @return HasMany<OAuthConnection, $this>
     */
    public function oauthConnections(): HasMany
    {
        return $this->hasMany(OAuthConnection::class);
    }

    /**
     * @return HasMany<ChangelogRead, $this>
     */
    public function changelogReads(): HasMany
    {
        return $this->hasMany(ChangelogRead::class);
    }

    /**
     * The apps this user pinned to their "Your apps" in the editor.
     *
     * @return BelongsToMany<NodeCategory, $this>
     */
    public function pinnedNodeCategories(): BelongsToMany
    {
        return $this->belongsToMany(NodeCategory::class, 'node_category_pins')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Workflow, $this>
     */
    public function favoriteWorkflows(): BelongsToMany
    {
        return $this->belongsToMany(Workflow::class, 'workflow_favorites')->withTimestamps();
    }

    /**
     * @return HasMany<AuthEventLog, $this>
     */
    public function authEvents(): HasMany
    {
        return $this->hasMany(AuthEventLog::class);
    }

    /**
     * @return HasMany<NotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * @return HasMany<AccountNotificationPreference, $this>
     */
    public function accountNotificationPreferences(): HasMany
    {
        return $this->hasMany(AccountNotificationPreference::class);
    }

    /**
     * The member's inbox, newest occurrence first. Replaces the trait's
     * relation so rows come back as `InboxNotification`, with the inbox
     * columns cast.
     *
     * @return MorphMany<InboxNotification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(InboxNotification::class, 'notifiable')->newestFirst();
    }

    /**
     * @return HasOne<ReferralCode, $this>
     */
    public function referralCode(): HasOne
    {
        return $this->hasOne(ReferralCode::class);
    }

    /**
     * The referral this user signed up through, if any.
     *
     * @return HasOne<Referral, $this>
     */
    public function referral(): HasOne
    {
        return $this->hasOne(Referral::class, 'referred_user_id');
    }

    /**
     * @return HasMany<Referral, $this>
     */
    public function referralsMade(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_user_id');
    }

    /**
     * Rewards this user received, as referrer or as the referred user.
     *
     * @return HasMany<ReferralReward, $this>
     */
    public function referralRewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class, 'recipient_user_id');
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }
}
