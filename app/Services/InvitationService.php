<?php

namespace App\Services;

use App\Enums\Limit;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function send(Workspace $workspace, User $inviter, array $data): Invitation
    {
        $email = $data['email'];

        if ($workspace->members()->where('users.email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This user is already a member of the workspace.'],
            ]);
        }

        if ($workspace->invitations()->pending()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['A pending invitation already exists for this email.'],
            ]);
        }

        $rawToken = Str::random(64);

        $invitation = $workspace->invitations()->create([
            'email' => $email,
            'role' => $data['role'],
            'personal_note' => $data['personal_note'] ?? null,
            'token_hash' => Invitation::hashToken($rawToken),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);

        Notification::route('mail', $email)
            ->notify(new WorkspaceInvitationNotification($invitation->load('workspace'), $rawToken));

        return $invitation;
    }

    /**
     * Send invitations to multiple emails. Skips already-members and already-invited emails
     * without throwing; returns the list of successfully created invitations.
     *
     * @param  array{emails: list<string>, role: string, personal_note?: string}  $data
     * @return list<Invitation>
     */
    public function sendBulk(Workspace $workspace, User $inviter, array $data): array
    {
        $role = $data['role'];
        $personalNote = $data['personal_note'] ?? null;
        $created = [];

        foreach ($data['emails'] as $email) {
            $email = trim($email);

            if ($workspace->members()->where('users.email', $email)->exists()) {
                continue;
            }

            if ($workspace->invitations()->pending()->where('email', $email)->exists()) {
                continue;
            }

            $rawToken = Str::random(64);

            $invitation = $workspace->invitations()->create([
                'email' => $email,
                'role' => $role,
                'personal_note' => $personalNote,
                'token_hash' => Invitation::hashToken($rawToken),
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays(7),
            ]);

            Notification::route('mail', $email)
                ->notify(new WorkspaceInvitationNotification($invitation->load('workspace'), $rawToken));

            $created[] = $invitation;
        }

        return $created;
    }

    public function accept(string $rawToken, User $user): Invitation
    {
        $invitation = Invitation::where('token_hash', Invitation::hashToken($rawToken))->firstOrFail();

        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages(['token' => ['Invitation already accepted.']]);
        }

        if ($invitation->isRevoked()) {
            throw ValidationException::withMessages(['token' => ['Invitation has been revoked.']]);
        }

        if ($invitation->isDeclined()) {
            throw ValidationException::withMessages(['token' => ['Invitation has been declined.']]);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages(['token' => ['Invitation has expired.']]);
        }

        if ($invitation->email !== $user->email) {
            abort(403, 'This invitation was not sent to your email address.');
        }

        $this->assertSeatAvailable($invitation->workspace);

        $workspace = $invitation->workspace;

        if (! $workspace->members()->where('users.id', $user->id)->exists()) {
            $workspace->members()->attach($user->id, [
                'id' => Str::uuid()->toString(),
                'role' => $invitation->role,
                'joined_at' => now(),
            ]);
        }

        $invitation->update(['accepted_at' => now()]);

        if (! $user->current_workspace_id) {
            $user->update(['current_workspace_id' => $workspace->id]);
        }

        return $invitation;
    }

    public function decline(string $rawToken, User $user): Invitation
    {
        $invitation = Invitation::where('token_hash', Invitation::hashToken($rawToken))->firstOrFail();

        if ($invitation->email !== $user->email) {
            abort(403, 'This invitation was not sent to your email address.');
        }

        if (! $invitation->isPending()) {
            throw ValidationException::withMessages(['token' => ['Invitation is no longer pending.']]);
        }

        $invitation->update(['declined_at' => now()]);

        return $invitation;
    }

    public function revoke(Invitation $invitation): Invitation
    {
        $invitation->update(['revoked_at' => now()]);

        return $invitation;
    }

    /**
     * Enforce the workspace plan's seat limit at the point a member actually
     * joins (invitation acceptance). Sending invites is never blocked; the seat
     * is only consumed on join, so this is the correct and only enforcement gate.
     */
    protected function assertSeatAvailable(Workspace $workspace): void
    {
        $plan = $workspace->currentPlan();

        if (! $plan || $plan->isUnlimited(Limit::Seats)) {
            return;
        }

        $limit = $plan->getLimit(Limit::Seats);

        if ($workspace->members()->count() >= $limit) {
            throw ValidationException::withMessages([
                'token' => ["This workspace has reached its seat limit ({$limit}) on the {$plan->name} plan. The owner needs to upgrade to add more members."],
            ]);
        }
    }
}
