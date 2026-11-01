<?php

namespace App\Services\SuperAdmin\Customers;

use App\Enums\SuperAdmin\SuperAdminAbility;
use App\Models\Runs\Run;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Str;

/**
 * One search box for users, workspaces and runs. Each kind is searched only
 * if the admin may view it, and the answer names the kinds that were left out
 * so the page can say so instead of looking as if nothing matched.
 */
class CustomerSearch
{
    public const MIN_LENGTH = 2;

    public const LIMIT = 8;

    public function __construct(private readonly CustomerAnnotations $annotations) {}

    /**
     * @return array{users: list<array<string, mixed>>, workspaces: list<array<string, mixed>>, runs: list<array<string, mixed>>, skipped: list<string>}
     */
    public function search(SuperAdmin $admin, string $term): array
    {
        $term = str_replace(['%', '\\'], '', trim($term));
        $result = ['users' => [], 'workspaces' => [], 'runs' => [], 'skipped' => []];

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return $result;
        }

        if ($admin->hasAbility(SuperAdminAbility::UsersView)) {
            $result['users'] = $this->users($term);
        } else {
            $result['skipped'][] = 'users';
        }

        if ($admin->hasAbility(SuperAdminAbility::WorkspacesView)) {
            $result['workspaces'] = $this->workspaces($term);
        } else {
            $result['skipped'][] = 'workspaces';
        }

        if ($admin->hasAbility(SuperAdminAbility::RunsView)) {
            $result['runs'] = $this->runs($term);
        } else {
            $result['skipped'][] = 'runs';
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function users(string $term): array
    {
        $users = User::query()
            ->where(function ($query) use ($term) {
                $query->whereLike('email', "%{$term}%")->orWhereLike('name', "%{$term}%");

                if (Str::isUuid($term)) {
                    $query->orWhere('id', $term);
                }
            })
            ->orderBy('email')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'email', 'suspended_at', 'email_verified_at']);

        $this->annotations->attach(CustomerSubjects::USER, $users);

        return $users->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'suspended_at' => $user->suspended_at,
            'email_verified_at' => $user->email_verified_at,
            'tags' => $user->account_tags,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function workspaces(string $term): array
    {
        $workspaces = Workspace::query()
            ->with('owner:id,name,email')
            ->where(function ($query) use ($term) {
                $query->whereLike('name', "%{$term}%")->orWhereLike('slug', "%{$term}%");

                if (Str::isUuid($term)) {
                    $query->orWhere('id', $term);
                }
            })
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'slug', 'owner_id', 'suspended_at']);

        $this->annotations->attach(CustomerSubjects::WORKSPACE, $workspaces);

        return $workspaces->map(fn (Workspace $workspace): array => [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'owner_email' => $workspace->owner?->email,
            'suspended_at' => $workspace->suspended_at,
            'tags' => $workspace->account_tags,
        ])->all();
    }

    /**
     * Runs are found by their id, whole or by its first characters (at least 8 hex digits).
     *
     * @return list<array<string, mixed>>
     */
    private function runs(string $term): array
    {
        if (! preg_match('/^[0-9a-f-]{8,36}$/i', $term)) {
            return [];
        }

        return Run::query()
            ->with(['workspace:id,name', 'workflow:id,name'])
            ->whereRaw('LOWER(CAST(id AS TEXT)) LIKE ?', [Str::lower($term).'%'])
            ->latest()
            ->limit(self::LIMIT)
            ->get(['id', 'workspace_id', 'workflow_id', 'status', 'created_at'])
            ->map(fn (Run $run): array => [
                'id' => $run->id,
                'status' => $run->status->value,
                'workspace' => $run->workspace === null ? null : ['id' => $run->workspace->id, 'name' => $run->workspace->name],
                'workflow' => $run->workflow?->name,
                'created_at' => $run->created_at,
            ])->all();
    }
}
