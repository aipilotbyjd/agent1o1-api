<?php

namespace App\Services\SuperAdmin\Models;

use App\Exceptions\ModelLifecycleException;
use App\Models\Ai\ModelCatalog;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\Ai\Operations\ModelLifecycle;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Carbon\CarbonInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Deprecating, retiring and restoring models for a super admin: the domain
 * rules live in `ModelLifecycle`; this adds the audit trail and turns a
 * refusal into the 409 the API answers with.
 */
class SuperAdminModelLifecycleService
{
    public function __construct(
        private readonly ModelLifecycle $lifecycle,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    public function deprecate(SuperAdmin $actor, ModelCatalog $model, string $replacementSlug, CarbonInterface $retiresAt, string $reason): ModelCatalog
    {
        $before = ['retires_at' => $model->retires_at?->toIso8601String(), 'replacement_slug' => $model->replacement_slug];

        $model = $this->attempt(fn () => $this->lifecycle->deprecate($model, $replacementSlug, $retiresAt));

        $this->audit->record($actor, 'models.deprecated', $model, $before, [
            'retires_at' => $retiresAt->toIso8601String(),
            'replacement_slug' => $replacementSlug,
        ], $reason);

        return $model;
    }

    /**
     * @return array{replacement: ?string, agents: int, assistants: int, workspaces: int}
     */
    public function retire(SuperAdmin $actor, ModelCatalog $model, ?string $replacementSlug, string $reason): array
    {
        $summary = $this->attempt(fn () => $this->lifecycle->retire($model, $replacementSlug));

        $this->audit->record($actor, 'models.retired', $model, ['is_active' => true], $summary, $reason);

        return $summary;
    }

    public function restore(SuperAdmin $actor, ModelCatalog $model, string $reason): ModelCatalog
    {
        $before = ['is_active' => $model->is_active, 'retires_at' => $model->retires_at?->toIso8601String(), 'replacement_slug' => $model->replacement_slug];

        $model = $this->attempt(fn () => $this->lifecycle->restore($model));

        $this->audit->record($actor, 'models.restored', $model, $before, ['is_active' => true], $reason);

        return $model;
    }

    /**
     * What retiring the model would move, for the confirmation screen.
     *
     * @return array{agents: int, assistants: int, workspaces: int}
     */
    public function usage(ModelCatalog $model): array
    {
        return $this->lifecycle->usage($model);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $action
     * @return T
     */
    private function attempt(callable $action): mixed
    {
        try {
            return $action();
        } catch (ModelLifecycleException $e) {
            throw new HttpException(409, $e->getMessage());
        }
    }
}
