<?php

namespace App\Exceptions;

use App\Models\Ai\ModelCatalog;
use App\Models\Billing\Plan;
use RuntimeException;

/**
 * A run, agent turn or assistant reply asked for a model the workspace's
 * current plan does not include, typically after a downgrade. Mapped to a
 * 402 response in `bootstrap/app.php`; a queued run or turn fails with this
 * message, so the member sees what to upgrade to.
 */
class ModelNotOnPlanException extends RuntimeException
{
    public function __construct(
        public readonly ModelCatalog $model,
        public readonly Plan $requiredPlan,
    ) {
        parent::__construct("{$model->display_name} needs the {$requiredPlan->name} plan or higher. Upgrade your plan to keep using it, or pick another model.");
    }
}
