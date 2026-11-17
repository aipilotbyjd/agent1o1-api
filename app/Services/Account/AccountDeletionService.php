<?php

namespace App\Services\Account;

use App\Exceptions\AccountDeletionBlockedException;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Auth\AuthService;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently deletes an account. A workspace row cascades with its owner,
 * so deleting a user blindly would take a whole team's workspace (and leave
 * its Stripe subscription billing) with it. This refuses while the account
 * owns a workspace that other people still use or that is still subscribed,
 * and otherwise deletes the owner's solo workspaces explicitly first.
 */
class AccountDeletionService
{
    public const string REASON_HAS_OTHER_MEMBERS = 'has_other_members';

    public const string REASON_ACTIVE_SUBSCRIPTION = 'active_subscription';

    public function __construct(
        private readonly AuthService $auth,
        private readonly WorkspaceService $workspaces,
    ) {}

    /**
     * Whether deleting must be confirmed with the account password. An
     * account created through Google or GitHub holds a random password
     * nobody knows, so it confirms with the typed email address alone.
     */
    public function requiresPassword(User $user): bool
    {
        return ! $user->oauthConnections()->exists();
    }

    /**
     * Owned workspaces that stop the account being deleted, and why.
     *
     * @return array<int, array{id: string, name: string, reasons: array<int, string>}>
     */
    public function blockers(User $user): array
    {
        return $user->ownedWorkspaces()
            ->withCount(['members as other_members_count' => fn ($members) => $members->where('user_id', '!=', $user->id)])
            ->get()
            ->map(function (Workspace $workspace): array {
                $reasons = [];

                if ($workspace->other_members_count > 0) {
                    $reasons[] = self::REASON_HAS_OTHER_MEMBERS;
                }

                if ($this->isStillBilled($workspace)) {
                    $reasons[] = self::REASON_ACTIVE_SUBSCRIPTION;
                }

                return ['id' => $workspace->id, 'name' => $workspace->name, 'reasons' => $reasons];
            })
            ->filter(fn (array $workspace): bool => $workspace['reasons'] !== [])
            ->values()
            ->all();
    }

    /**
     * @throws AccountDeletionBlockedException
     */
    public function delete(User $user): void
    {
        $blockers = $this->blockers($user);

        if ($blockers !== []) {
            throw new AccountDeletionBlockedException($blockers);
        }

        $avatar = $user->avatar;

        DB::transaction(function () use ($user): void {
            $user->ownedWorkspaces()->get()->each(fn (Workspace $workspace) => $this->workspaces->delete($workspace));

            $user->dataExports()->get()->each->delete();

            $this->auth->deleteAccount($user);
        });

        if ($avatar !== null) {
            Storage::disk('public')->delete($avatar);
        }
    }

    /**
     * A subscription that will charge again. One already canceled and only
     * running out its paid period does not block: it ends on its own.
     */
    private function isStillBilled(Workspace $workspace): bool
    {
        $subscription = $workspace->activeSubscription();

        return $subscription !== null && ! $subscription->canceled();
    }
}
