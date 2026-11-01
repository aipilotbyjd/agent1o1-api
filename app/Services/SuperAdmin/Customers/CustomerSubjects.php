<?php

namespace App\Services\SuperAdmin\Customers;

use App\Enums\SuperAdmin\SuperAdminAbility;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The two kinds of record an account page is about, and the label the audit
 * log and the notes use for each.
 */
final class CustomerSubjects
{
    public const USER = 'user';

    public const WORKSPACE = 'workspace';

    public const TYPES = [self::USER, self::WORKSPACE];

    public static function label(Model $subject): string
    {
        return match (true) {
            $subject instanceof User => self::USER,
            $subject instanceof Workspace => self::WORKSPACE,
            default => throw new InvalidArgumentException('Not a customer record: '.$subject::class),
        };
    }

    /**
     * The ability that lets an admin look at this kind of record.
     */
    public static function viewAbility(string $type): SuperAdminAbility
    {
        return $type === self::USER ? SuperAdminAbility::UsersView : SuperAdminAbility::WorkspacesView;
    }
}
