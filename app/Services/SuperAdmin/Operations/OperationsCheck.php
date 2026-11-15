<?php

namespace App\Services\SuperAdmin\Operations;

/**
 * One automatic check on the checklist. It looks at the platform and says how
 * it is doing; it never changes anything.
 */
interface OperationsCheck
{
    /**
     * The key a task stores in its `check` column.
     */
    public function key(): string;

    public function evaluate(): CheckResult;
}
