<?php

namespace App\Enums\Setup;

/**
 * Who has the final say over a dataset's rows once they exist.
 */
enum Ownership: string
{
    /** The code is the source of truth: a sync overwrites the rows. */
    case CodeOwned = 'code_owned';

    /** Created once, then maintained by an admin: a sync never overwrites. */
    case AdminOwned = 'admin_owned';

    /** The code sets identity, an admin keeps their own settings on top. */
    case Shared = 'shared';

    public function label(): string
    {
        return match ($this) {
            self::CodeOwned => 'Code',
            self::AdminOwned => 'Admin',
            self::Shared => 'Shared',
        };
    }
}
