<?php

namespace App\Services\Onboarding;

use App\Enums\Onboarding\LifecycleEmail;
use App\Models\Onboarding\LifecycleEmailSend;
use App\Models\Runs\Run;
use App\Models\User;
use App\Notifications\Onboarding\LifecycleEmailNotification;
use App\Services\Templates\TemplateRecommendations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Sends the emails of a new account's first weeks (`LifecycleEmail`) as they
 * come due. Each email goes once, and only while it still applies when it is
 * due: a nudge to run a first workflow is never sent to someone who has.
 *
 * Who is left alone: suspended accounts, anyone who opted out of marketing
 * email, and (apart from the welcome) anyone who has not verified their
 * address. An email goes out within `SEND_WINDOW_HOURS` of coming due or not
 * at all, so switching this on never mails every old account at once, and
 * at most one email goes out per `MIN_HOURS_BETWEEN`, so the sequence never
 * piles up in someone's inbox.
 */
final class LifecycleEmails
{
    /**
     * How long after coming due an email may still be sent.
     */
    public const int SEND_WINDOW_HOURS = 48;

    /**
     * The least time between two lifecycle emails to one person.
     */
    public const int MIN_HOURS_BETWEEN = 20;

    /**
     * How far back "lately" reaches for the recap and win-back emails.
     */
    private const int RECENT_DAYS = 14;

    public function __construct(private readonly TemplateRecommendations $templates) {}

    /**
     * Sends every lifecycle email that is due and returns how many went out.
     */
    public function sendDue(): int
    {
        $sent = 0;

        foreach (LifecycleEmail::cases() as $email) {
            $this->due($email)->chunkById(100, function ($users) use ($email, &$sent): void {
                foreach ($users as $user) {
                    if ($this->trySend($email, $user)) {
                        $sent++;
                    }
                }
            });
        }

        return $sent;
    }

    /**
     * Whether the email still says something true to this person.
     */
    public function applies(LifecycleEmail $email, User $user): bool
    {
        return match ($email) {
            LifecycleEmail::Welcome => true,
            LifecycleEmail::FirstRunNudge, LifecycleEmail::RoleTemplates => ! $this->succeededRuns($user)->exists(),
            LifecycleEmail::AutomateIt => $this->succeededRuns($user)->exists() && ! $this->hasActiveTrigger($user),
            LifecycleEmail::FirstWeekRecap => $this->succeededRuns($user)->where('created_at', '>=', now()->subWeek())->exists(),
            LifecycleEmail::WinBack, LifecycleEmail::LastCheckIn => ! $this->succeededRuns($user)->where('created_at', '>=', now()->subDays(self::RECENT_DAYS))->exists(),
        };
    }

    private function trySend(LifecycleEmail $email, User $user): bool
    {
        if (! $this->applies($email, $user) || ($email->isCapped() && $this->sentRecently($user))) {
            return false;
        }

        // Claimed before sending, so a retried or overlapping run cannot send it twice.
        try {
            LifecycleEmailSend::query()->create(['user_id' => $user->id, 'email' => $email, 'sent_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        $user->notify(new LifecycleEmailNotification($email, $this->details($email, $user)));

        return true;
    }

    /**
     * Users this email is due for and has not been sent to.
     *
     * @return Builder<User>
     */
    private function due(LifecycleEmail $email): Builder
    {
        $dueAt = now()->subMinutes($email->dueAfterMinutes());

        return User::query()
            ->whereNull('suspended_at')
            ->whereNull('marketing_opted_out_at')
            ->when($email !== LifecycleEmail::Welcome, fn (Builder $query) => $query->whereNotNull('email_verified_at'))
            ->where('created_at', '<=', $dueAt)
            ->where('created_at', '>', $dueAt->copy()->subHours(self::SEND_WINDOW_HOURS))
            ->whereDoesntHave('lifecycleEmailSends', fn (Builder $query) => $query->where('email', $email->value));
    }

    /**
     * Whether a capped email went out lately. The welcome is not capped and
     * does not count, so the first-run nudge can follow it within the hour.
     */
    private function sentRecently(User $user): bool
    {
        $uncapped = array_map(
            fn (LifecycleEmail $email): string => $email->value,
            array_filter(LifecycleEmail::cases(), fn (LifecycleEmail $email): bool => ! $email->isCapped()),
        );

        return $user->lifecycleEmailSends()
            ->whereNotIn('email', $uncapped)
            ->where('sent_at', '>', now()->subHours(self::MIN_HOURS_BETWEEN))
            ->exists();
    }

    /**
     * @return Builder<Run>
     */
    private function succeededRuns(User $user): Builder
    {
        return Run::query()->succeededWorkflowRuns()->where('triggered_by', $user->id);
    }

    private function hasActiveTrigger(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null && $workspace->triggers()->where('is_active', true)->exists();
    }

    /**
     * What the email needs to say: where its links go, and for the template
     * and recap emails, what to show.
     *
     * @return array{workspace_id: string|null, templates?: array<int, array<string, mixed>>, runs_this_week?: int}
     */
    private function details(LifecycleEmail $email, User $user): array
    {
        $workspace = $user->currentWorkspace;
        $details = ['workspace_id' => $workspace?->id];

        return match ($email) {
            LifecycleEmail::RoleTemplates => [
                ...$details,
                'templates' => $workspace === null ? [] : $this->templates->forRole($workspace, $user->job_role),
            ],
            LifecycleEmail::FirstWeekRecap => [
                ...$details,
                'runs_this_week' => $this->succeededRuns($user)->where('created_at', '>=', now()->subWeek())->count(),
            ],
            default => $details,
        };
    }
}
